@extends('layouts.public')

@section('title', $asset->passport_code . ' · Paspor SIRKEL')
@section('meta_description', 'Paspor SIRKEL untuk ' . $asset->passport_code . ' menampilkan status dan riwayat penanganan elektronik tanpa membuka identitas pribadi pemilik.')
@section('robots', 'noindex,follow')

@section('content')
    @php
        $verifiedOutcome = $asset->final_path && \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path);
        $hasUnverifiedClosure = $asset->final_path === 'UNVERIFIED_FINAL_TREATMENT';
        $returnedToOwner = $asset->final_path === 'RETURNED_TO_OWNER';
        $splitParent = $asset->final_path === 'SPLIT_TO_SUB_BATCHES';
        $intermediateRecovery = $asset->final_path === 'RECEIVED_BY_RECOVERY_PARTNER';
        $publicStatus = $asset->final_path
            ? \App\Support\SirkelUi::label($asset->final_path)
            : \App\Support\SirkelUi::assetProgress($asset->status, null);
    @endphp
    <section class="section">
        <div class="container" style="max-width:900px">
            <div class="split">
                <div>
                    <div class="eyebrow">Paspor SIRKEL</div>
                    <h1 style="font-size:42px">{{ $asset->passport_code }}</h1>
                    <p class="lead">Riwayat penanganan barang yang dapat dilihat tanpa membuka data pribadi pemilik.</p>
                </div>
                <img src="{{ route('passport.qr', $asset->passport_code) }}" width="112" height="112"
                    alt="QR Paspor SIRKEL {{ $asset->passport_code }}">
            </div>

            <div class="grid-3" style="margin:22px 0">
                <div class="card">
                    <div class="metric-label">Barang</div>
                    <strong>{{ $asset->custom_item_name ?: $asset->category->name }}</strong>
                    <div class="text-sm muted">
                        {{ trim(($asset->brand ?? '') . ' ' . ($asset->model_name ?? '')) ?: 'Merek/model tidak dicantumkan' }}
                    </div>
                </div>
                <div class="card">
                    <div class="metric-label">Pencatatan</div>
                    <strong>{{ \App\Support\SirkelUi::label($asset->tracking_type) }}</strong>
                    <div class="text-sm muted">
                        {{ $asset->quantity }} unit
                        @if($asset->verified_weight_kg)
                            · {{ number_format($asset->verified_weight_kg, 3, ',', '.') }} kg
                        @endif
                    </div>
                </div>
                <div class="card">
                    <div class="metric-label">Asal</div>
                    <strong>{{ collect([$asset->origin_village, $asset->origin_district])->filter()->implode(', ') ?: 'Surabaya' }}</strong>
                    <div class="text-sm muted">Alamat lengkap tidak ditampilkan</div>
                </div>
            </div>

            <div class="card">
                <div class="split">
                    <h3>Status Penanganan</h3>
                    <span class="badge {{ $verifiedOutcome ? 'success' : 'warning' }}">{{ $publicStatus }}</span>
                </div>

                @if($verifiedOutcome)
                    <div class="alert success mt-16">Hasil penanganan sudah dikonfirmasi.</div>
                @elseif($returnedToOwner)
                    <div class="alert warning mt-16">Barang dikembalikan kepada pemilik dan tidak masuk perhitungan dampak
                        sirkular.</div>
                @elseif($splitParent)
                    <div class="alert mt-16">Kelompok barang telah dipisahkan. Penanganan berlanjut pada paspor kelompok hasil.
                    </div>
                @elseif($intermediateRecovery)
                    <div class="alert warning mt-16">Barang sudah diterima mitra pemulihan. Hasil pemulihan material belum
                        dikonfirmasi.</div>
                @elseif($hasUnverifiedClosure)
                    <div class="alert warning mt-16">Riwayat ditutup tanpa hasil akhir yang dapat dikonfirmasi.</div>
                @else
                    <div class="alert mt-16">Penanganan masih berlangsung.</div>
                @endif
            </div>

            <div class="card mt-16">
                <div class="split">
                    <h3>Perjalanan Barang</h3><span class="badge">{{ $asset->events->count() }} catatan</span>
                </div>
                <div class="timeline" style="margin-top:18px">
                    @forelse($asset->events as $event)
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <div>
                                <strong>{{ $event->title }}</strong>
                                <div class="text-sm muted">{{ $event->occurred_at->format('d M Y H:i') }}</div>
                                @if($event->description)
                                <p class="text-sm muted">{{ $event->description }}</p>@endif
                            </div>
                        </div>
                    @empty
                        <div class="empty">Belum ada riwayat perjalanan.</div>
                    @endforelse
                </div>
            </div>

            <div class="text-sm muted mt-16">Paspor publik tidak menampilkan nama pemilik, WhatsApp, alamat lengkap,
                koordinat rumah, nomor serial, atau dokumen identitas.</div>
        </div>
    </section>
@endsection