@extends('layouts.app')

@section('title','Moderasi · SIRKEL')
@section('topbar','Laporan & Moderasi')

@section('content')
<div class="page-head">
    <div>
        <h2>Laporan Operasional</h2>
        <p>Tinjau laporan dan bantu kasus yang membutuhkan tindak lanjut.</p>
    </div>
</div>

<div class="stack">
@forelse($issues as $i)
    @php
        $context = is_array($i->context_json) ? $i->context_json : [];
        $candidates = $candidateMap->get($i->id, collect());
        $requiredCapability = $i->asset ? app(\App\Services\AssetFlowService::class)->initialCapability($i->asset) : null;
    @endphp
    <article class="card stack">
        <div class="split">
            <div>
                <div class="cluster">
                    <h3>{{ \App\Support\SirkelUi::label($i->category) }}</h3>
                    <span class="badge {{ $i->status==='open'?'warning':($i->status==='resolved'?'success':'') }}">{{ \App\Support\SirkelUi::label($i->status) }}</span>
                </div>
                <p class="text-sm muted">
                    Pelapor: {{ $i->reporter->name }} · {{ $i->created_at->format('d M Y H:i') }}
                    @if($i->asset) · {{ $i->asset->passport_code }} @endif
                </p>
            </div>
        </div>

        <p>{{ $i->description }}</p>

        @if($i->category === 'matching_help' && $i->asset)
            <div class="hint-box">
                <strong>Bantuan pencarian mitra</strong>
                <div class="detail-grid mt-8">
                    <div class="detail-item"><span class="detail-label">Barang</span><div class="detail-value">{{ $i->asset->custom_item_name ?: $i->asset->category->name }}</div></div>
                    <div class="detail-item"><span class="detail-label">Layanan utama</span><div class="detail-value">{{ \App\Support\SirkelUi::label($requiredCapability) }}</div></div>
                    <div class="detail-item"><span class="detail-label">Cara penyerahan</span><div class="detail-value">{{ \App\Support\SirkelUi::label($context['method'] ?? '-') }}</div></div>
                    <div class="detail-item"><span class="detail-label">Tujuan penyerahan</span><div class="detail-value">{{ \App\Support\SirkelUi::label($context['handover_type'] ?? '-') }}</div></div>
                    @if(filled($context['district'] ?? null))
                        <div class="detail-item"><span class="detail-label">Wilayah</span><div class="detail-value">{{ $context['village'] ?? '' }}{{ filled($context['village'] ?? null) ? ', ' : '' }}{{ $context['district'] }}, Surabaya</div></div>
                    @endif
                    @if(filled($context['requested_date'] ?? null))
                        <div class="detail-item"><span class="detail-label">Jadwal yang dipilih warga</span><div class="detail-value">{{ \Carbon\Carbon::parse($context['requested_date'])->format('d M Y') }} @if(filled($context['time_start'] ?? null)) · {{ $context['time_start'] }}{{ filled($context['time_end'] ?? null) ? '–'.$context['time_end'] : '' }} @endif</div></div>
                    @endif
                </div>
            </div>

            @if($i->request)
                <div class="alert {{ $i->request->status === 'accepted' ? 'success' : ($i->request->status === 'pending' ? 'warning' : '') }}">
                    <strong>Permintaan ke mitra:</strong>
                    {{ $i->request->partner->business_name ?? '-' }} · {{ \App\Support\SirkelUi::handoverStatus($i->request->status) }}
                    @if($i->request->status === 'declined' && $i->request->decline_reason)
                        <div class="text-sm mt-8">Alasan: {{ $i->request->decline_reason }}</div>
                    @endif
                </div>
            @endif

            @if(in_array($i->status, ['open','in_review'], true) && !$i->asset->requests()->whereNotIn('status', \App\Models\HandoverRequest::TERMINAL_STATUSES)->exists())
                <section class="stack">
                    <div>
                        <h4>Hubungkan ke Mitra</h4>
                        <p class="text-sm muted">SIRKEL menampilkan mitra aktif yang memiliki layanan utama yang dibutuhkan. Jika kategori belum termasuk cakupan rutin mitra, mitra tetap harus meninjau dan menyetujuinya sendiri.</p>
                    </div>

                    @forelse($candidates as $partner)
                        <div class="card">
                            <div class="split">
                                <div>
                                    <div class="cluster">
                                        <strong>{{ $partner->business_name }}</strong>
                                        <span class="badge">{{ number_format((float)$partner->match_distance_km,1) }} km</span>
                                        @if($partner->category_supported)
                                            <span class="badge success">Kategori sesuai</span>
                                        @else
                                            <span class="badge warning">Perlu konfirmasi kategori</span>
                                        @endif
                                        @if(($context['method'] ?? null) === 'pickup')
                                            <span class="badge {{ $partner->within_radius ? 'success' : 'warning' }}">{{ $partner->within_radius ? 'Dalam radius' : 'Di luar radius' }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm muted mt-8">{{ $partner->village ? $partner->village.', ' : '' }}{{ $partner->district }} · radius jemput {{ number_format((float)$partner->pickup_radius_km,1) }} km</div>
                                    @if(!$partner->category_supported)
                                        <div class="text-sm mt-8">Kategori barang belum tercantum dalam cakupan rutin mitra ini. Permintaan akan dikirim sebagai permintaan bantuan dan mitra dapat menolak jika tidak dapat menangani.</div>
                                    @endif
                                </div>
                                <form method="post" action="{{ route('admin.issues.offer-partner',$i) }}">
                                    @csrf
                                    <input type="hidden" name="partner_profile_id" value="{{ $partner->id }}">
                                    <button class="btn btn-primary">Tawarkan ke Mitra</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="alert warning">
                            Belum ada mitra aktif dengan layanan {{ \App\Support\SirkelUi::label($requiredCapability) }} yang dapat dihubungi. Periksa data layanan mitra atau beri catatan kepada warga.
                            <div class="mt-8"><a class="btn btn-sm" href="{{ route('admin.partners.index') }}">Lihat Data Mitra</a></div>
                        </div>
                    @endforelse
                </section>
            @elseif($i->request && !in_array($i->request->status, \App\Models\HandoverRequest::TERMINAL_STATUSES, true))
                <div class="text-sm muted">Tunggu tanggapan mitra sebelum memilih mitra lain.</div>
            @endif
        @elseif($i->request)
            <div class="alert">Permintaan #{{ $i->request->id }} · {{ $i->request->partner->business_name??'-' }} · {{ \App\Support\SirkelUi::handoverStatus($i->request->status) }}</div>
        @endif

        <form class="form-grid" method="post" action="{{ route('admin.issues.update',$i) }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label>Status</label>
                <select class="select" name="status">
                    <option value="open" {{ $i->status==='open'?'selected':'' }}>Terbuka</option>
                    <option value="in_review" {{ $i->status==='in_review'?'selected':'' }}>Sedang ditinjau</option>
                    <option value="resolved" {{ $i->status==='resolved'?'selected':'' }}>Selesai ditangani</option>
                    <option value="dismissed" {{ $i->status==='dismissed'?'selected':'' }}>Ditutup tanpa tindakan</option>
                </select>
            </div>
            <div class="field"><label>Catatan admin</label><input class="input" name="admin_note" value="{{ $i->admin_note }}"></div>
            <div class="field full"><button class="btn">Simpan Catatan</button></div>
        </form>
    </article>
@empty
    <div class="card empty">Tidak ada laporan.</div>
@endforelse
</div>

{{ $issues->links() }}
@endsection
