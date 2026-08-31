@extends('layouts.app')

@section('title','Kelola Mitra · SIRKEL')
@section('topbar','Mitra')

@section('content')
@php
    $approved = $partner->verification_status === 'approved';
    $adminActive = ($partner->admin_status ?? 'inactive') === 'active';
@endphp

<div class="page-head">
    <div>
        <h2>{{ $partner->business_name }}</h2>
        <p>{{ $partner->responsible_name }} · {{ $partner->user->email }}</p>
    </div>
    <div class="cluster">
        <span class="badge {{ $approved?'success':($partner->verification_status==='rejected'?'danger':'warning') }}">
            Verifikasi: {{ \App\Support\SirkelUi::label($partner->verification_status) }}
        </span>
        @if($approved)
            <span class="badge {{ $adminActive?'success':'danger' }}">
                Operasional: {{ \App\Support\SirkelUi::label($partner->admin_status) }}
            </span>
        @endif
    </div>
</div>

<div class="two-col">
    <div class="stack">
        <div class="card">
            <h3>Data Operasional</h3>
            <div class="form-grid">
                <div><span class="metric-label">WhatsApp</span><strong>+{{ $partner->phone }}</strong></div>
                <div><span class="metric-label">Radius penjemputan</span><strong>{{ $partner->pickup_radius_km }} km</strong></div>
                <div class="field full"><span class="metric-label">Alamat</span><strong>{{ $partner->address }}</strong><div class="text-sm muted">{{ $partner->village }}, {{ $partner->district }}, Surabaya</div></div>
                <div><span class="metric-label">Koordinat</span><span class="mono">{{ $partner->latitude }}, {{ $partner->longitude }}</span></div>
                <div><span class="metric-label">Jam</span><strong>{{ $partner->operating_hours_json['display']??'-' }}</strong></div>
                @if($approved)
                    <div><span class="metric-label">Penerimaan permintaan</span><strong>{{ $adminActive ? ($partner->accepting_requests ? 'Sedang menerima' : 'Dijeda mitra') : 'Tidak tersedia' }}</strong></div>
                @endif
            </div>
            @if($partner->place_photo_path)
                <img class="mt-16" style="max-height:300px;border-radius:10px;border:1px solid var(--border)" src="{{ asset('storage/'.$partner->place_photo_path) }}" alt="Foto tempat mitra">
            @endif
        </div>

        <div class="card">
            <h3>Kategori Diterima</h3>
            <div class="cluster">
                @forelse($partner->acceptedCategories as $category)
                    <span class="tag">{{ $category->name }}</span>
                @empty
                    <span class="muted">Belum ada kategori yang dipilih.</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h3>Verifikasi Identitas</h3>
            @if($partner->identity_file_path)
                <p class="muted">KTP tersimpan privat dan dijadwalkan terhapus {{ config('sirkel.ktp_retention_days') }} hari setelah verifikasi.</p>
                <a class="btn" href="{{ route('admin.partners.identity',$partner) }}">Buka KTP</a>
            @else
                <div class="alert warning">File KTP sudah dihapus sesuai retensi atau belum tersedia. Riwayat verifikasi tetap tersimpan.</div>
            @endif
            @if($partner->identity_delete_after)
                <p class="text-sm muted">Jadwal hapus: {{ $partner->identity_delete_after->format('d M Y H:i') }}</p>
            @endif
        </div>

        @if(!$approved)
            <div class="card">
                <h3>{{ $partner->verification_status === 'rejected' ? 'Tinjau Ulang Pengajuan' : 'Keputusan Admin' }}</h3>
                <form class="stack" method="post" action="{{ route('admin.partners.review',$partner) }}">
                    @csrf
                    <div class="field validation-group" data-validation-group="capabilities" data-required-group="capabilities" data-required-message="Pilih minimal satu layanan mitra yang disetujui.">
                        <label>Layanan yang disetujui *</label>
                        <div class="stack choice-group">
                            @foreach($partner->capabilities as $capability)
                                <label class="chip-check"><input type="checkbox" name="capabilities[]" value="{{ $capability->capability }}" {{ in_array($capability->capability, (array) old('capabilities', $partner->capabilities->where('status','approved')->pluck('capability')->all()), true)?'checked':'' }}><span>{{ \App\Support\SirkelUi::label($capability->capability) }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="field"><label>Catatan peninjauan</label><textarea class="textarea" name="note">{{ old('note') }}</textarea></div>
                    <div class="cluster">
                        <button class="btn btn-primary" name="decision" value="approved">Setujui Mitra</button>
                        @if($partner->verification_status !== 'rejected')
                            <button class="btn btn-danger" name="decision" value="rejected">Tolak Pengajuan</button>
                        @endif
                    </div>
                </form>
            </div>
        @else
            <div class="card">
                <h3>Pengelolaan Mitra</h3>
                <form class="stack" method="post" action="{{ route('admin.partners.manage',$partner) }}">
                    @csrf
                    @method('PUT')
                    <div class="field validation-group" data-validation-group="capabilities" data-required-group="capabilities" data-required-message="Pilih minimal satu layanan yang tetap diizinkan untuk mitra ini.">
                        <label>Layanan yang diizinkan *</label>
                        <div class="stack choice-group">
                            @foreach($partner->capabilities as $capability)
                                <label class="chip-check"><input type="checkbox" name="capabilities[]" value="{{ $capability->capability }}" {{ in_array($capability->capability, (array) old('capabilities', $partner->capabilities->where('status','approved')->pluck('capability')->all()), true)?'checked':'' }}><span>{{ \App\Support\SirkelUi::label($capability->capability) }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="field"><label>Catatan perubahan</label><textarea class="textarea" name="note" placeholder="Opsional. Jelaskan perubahan jika diperlukan.">{{ old('note') }}</textarea></div>
                    <button class="btn btn-primary">Simpan Perubahan</button>
                </form>

                <div class="divider"></div>
                <div class="split partner-admin-status-box">
                    <div>
                        <strong>{{ $adminActive ? 'Mitra sedang aktif' : 'Mitra sedang dinonaktifkan' }}</strong>
                        <div class="text-sm muted">{{ $adminActive ? 'Mitra dapat menerima permintaan baru selama penerimaan permintaan juga dinyalakan oleh mitra.' : 'Mitra tidak muncul dalam rekomendasi, daftar publik, atau target pengalihan baru.' }}</div>
                    </div>
                    <form method="post" action="{{ route('admin.partners.status',$partner) }}">
                        @csrf
                        <input type="hidden" name="admin_status" value="{{ $adminActive ? 'inactive' : 'active' }}">
                        <button class="btn {{ $adminActive ? 'btn-danger' : 'btn-primary' }}">{{ $adminActive ? 'Nonaktifkan Mitra' : 'Aktifkan Kembali' }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
