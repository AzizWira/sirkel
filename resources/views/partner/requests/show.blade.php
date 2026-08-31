@extends('layouts.app')

@section('title', 'Permintaan '.$handover->asset->passport_code.' · SIRKEL')
@section('topbar', 'Detail Permintaan')

@section('content')
@php
    $asset = $handover->asset;
    $profile = auth()->user()->partnerProfile;
    $accepted = in_array($handover->status, ['accepted','offered','offer_accepted','completed'], true);
    $offer = $handover->offers->where('is_current', true)->first();
    $handoverType = $handover->effectiveHandoverType();
    $sale = $handoverType === 'sale';
    $readyToReceive = $handover->readyForPhysicalHandover()
        && $handover->schedule_status !== 'proposed'
        && (! $handover->requested_date || $handover->schedule_status === 'accepted');
    $hasActiveCustody = $asset->custody->where('partner_profile_id', $profile->id)->whereNull('released_at')->isNotEmpty();
    $step = match($handover->status) {
        'pending' => 1,
        'accepted', 'offered' => 2,
        'offer_accepted' => 3,
        'completed' => 4,
        default => 1,
    };
@endphp

<div class="page-head">
    <div>
        <div class="cluster">
            <span class="badge">{{ $asset->passport_code }}</span>
            <span class="badge {{ $handover->status === 'completed' ? 'success' : '' }}">{{ \App\Support\SirkelUi::handoverStatus($handover->status) }}</span>
            @if($handover->outside_radius)<span class="badge warning">Di luar radius reguler</span>@endif
        </div>
        <h2 style="margin-top:8px">{{ $asset->custom_item_name ?: $asset->category->name }}</h2>
        <p>{{ \App\Support\SirkelUi::label($handover->method) }} · {{ number_format((float)$handover->distance_km, 1) }} km · {{ \App\Support\SirkelUi::label($handoverType) }}</p>
    </div>
    <a class="btn" href="{{ route('partner.requests.index') }}">Kembali</a>
</div>

<div class="flow-guide card">
    @foreach([
        1 => ['Tinjau permintaan','Terima atau tolak.'],
        2 => ['Kesepakatan','Penawaran jika diperlukan.'],
        3 => ['Penyerahan','Atur waktu dan terima barang.'],
        4 => ['Barang ditangani','Pemeriksaan dilakukan di menu berikutnya.'],
    ] as $n => $meta)
        <div class="flow-guide-item {{ $n < $step ? 'done' : ($n === $step ? 'current' : '') }}">
            <span>{{ $n < $step ? '✓' : $n }}</span><div><strong>{{ $meta[0] }}</strong><small>{{ $meta[1] }}</small></div>
        </div>
        @if($n < 4)<div class="flow-guide-arrow" aria-hidden="true"></div>@endif
    @endforeach
</div>

@if($matchingHelpIssue)
    <div class="alert warning mt-16">
        <strong>Permintaan bantuan dari SIRKEL.</strong> Warga belum menemukan mitra melalui pencarian biasa. Periksa barang dan kebutuhan layanannya terlebih dahulu; terima hanya jika Anda memang dapat menanganinya.
    </div>
@endif

@if($handoverType === 'donation')
    <div class="alert warning">
        <strong>Tujuan warga: Donasi.</strong> Tahap saat ini: <strong>{{ \App\Support\SirkelUi::label(app(\App\Services\AssetFlowService::class)->initialCapability($asset)) }}</strong>. Donasi dicatat selesai setelah barang benar-benar disalurkan.
    </div>
@endif

@if($handover->status === 'completed')
    <div class="next-action-card success-state mt-16">
        <div class="next-action-kicker">Penyerahan warga → mitra sudah selesai</div>
        @if($hasActiveCustody && !$asset->final_path)
            <h3>Barang sekarang masuk tahap pemeriksaan mitra</h3>
            <p>Penyerahan sudah selesai. Lanjutkan pemeriksaan dari menu <strong>Barang Ditangani</strong>.</p>
            <a class="btn btn-primary" href="{{ route('partner.assets.show', $asset) }}">Lanjut Tangani Barang</a>
        @elseif($asset->final_path)
            <h3>{{ \App\Support\SirkelUi::label($asset->final_path) }}</h3>
            <p>Penanganan barang sudah selesai.</p>
            <a class="btn" href="{{ route('partner.assets.show', $asset) }}">Lihat Riwayat Penanganan</a>
        @else
            <h3>Penyerahan selesai</h3>
            <p>Permintaan ini sudah ditutup.</p>
        @endif
    </div>
@endif

<div class="two-col mt-16">
    <div class="stack">
        <section class="card">
            <div class="split"><h3>Barang yang Diajukan</h3><span class="badge">{{ \App\Support\SirkelUi::label($asset->preliminary_path, 'Belum dicek') }}</span></div>
            <p>{{ $asset->description ?: 'Warga tidak menambahkan keterangan kondisi.' }}</p>
            <div class="detail-grid">
                <div class="detail-item"><span class="detail-label">Merek / model</span><div class="detail-value">{{ trim(($asset->brand ?? '').' '.($asset->model_name ?? '')) ?: 'Tidak diisi' }}</div></div>
                <div class="detail-item"><span class="detail-label">Perkiraan berat warga</span><div class="detail-value">{{ $asset->estimated_weight_kg ? number_format((float)$asset->estimated_weight_kg,3,',','.').' kg' : 'Tidak diisi' }}</div></div>
            </div>
            @if($asset->photos->count())
                <div class="asset-photos mt-16">@foreach($asset->photos as $photo)<img src="{{ asset('storage/'.$photo->path) }}" alt="Foto barang">@endforeach</div>
            @endif
        </section>

        <section class="card">
            <h3>Lokasi & Kontak</h3>
            @if(!$accepted)
                <div class="alert warning">Alamat lengkap dan WhatsApp tersedia setelah permintaan diterima.</div>
                <div><strong>{{ $handover->pickup_village ? $handover->pickup_village.', ' : '' }}{{ $handover->pickup_district }}</strong></div>
                @if($handover->method === 'pickup')
                    <div class="text-sm muted mt-8">Peta titik penjemputan akan muncul setelah permintaan diterima agar lokasi tepat warga tidak terbuka sebelum mitra menyetujui permintaan.</div>
                @endif
            @else
                <div class="detail-grid">
                    <div class="detail-item"><span class="detail-label">Warga</span><div class="detail-value">{{ $handover->user->name }}</div></div>
                    <div class="detail-item"><span class="detail-label">WhatsApp</span><div class="detail-value">+{{ $handover->user->whatsapp }}</div><a class="text-sm" style="color:var(--primary)" target="_blank" href="https://wa.me/{{ $handover->user->whatsapp }}">Hubungi via WhatsApp ↗</a></div>
                    @if($handover->method === 'pickup')
                        <div class="detail-item" style="grid-column:1/-1"><span class="detail-label">Alamat penjemputan</span><div class="detail-value">{{ $handover->pickup_address }}</div><div class="text-sm muted">{{ $handover->pickup_village ? $handover->pickup_village.', ' : '' }}{{ $handover->pickup_district }}, Surabaya</div></div>
                    @else
                        <div class="detail-item" style="grid-column:1/-1"><span class="detail-label">Cara penyerahan</span><div class="detail-value">Warga akan mengantar barang ke lokasi mitra</div><div class="text-sm muted">Titik rumah warga tidak ditampilkan kepada mitra untuk metode antar langsung.</div></div>
                    @endif
                </div>
                @if($handover->method === 'pickup' && $handover->pickup_latitude && $handover->pickup_longitude)
                    <input type="hidden" id="request-lat" value="{{ $handover->pickup_latitude }}">
                    <input type="hidden" id="request-lng" value="{{ $handover->pickup_longitude }}">
                    <div id="request-map" class="map-box mt-16" data-auto-map data-readonly="true" data-lat-input="request-lat" data-lng-input="request-lng" data-lat="{{ $handover->pickup_latitude }}" data-lng="{{ $handover->pickup_longitude }}" data-zoom="15"></div>
                    <div class="map-link-output">
                        <div class="map-link-output-copy">
                            <span class="text-sm muted">Titik penjemputan</span>
                            <a class="map-link-anchor" target="_blank" rel="noopener" href="{{ app(\App\Services\MapLinkService::class)->canonicalUrl($handover->pickup_latitude, $handover->pickup_longitude) }}">Buka di Google Maps</a>
                        </div>
                    </div>
                @endif
            @endif
        </section>

        @if($accepted)
            <section class="card">
                <h3>Jadwal Penyerahan</h3>
                <div class="detail-grid">
                    <div class="detail-item"><span class="detail-label">Jadwal warga</span><div class="detail-value">{{ $handover->requested_date?->format('d M Y') ?? 'Belum ditentukan' }} @if($handover->requested_time_start)· {{ substr($handover->requested_time_start,0,5) }}{{ $handover->requested_time_end ? '–'.substr($handover->requested_time_end,0,5) : '' }}@endif</div></div>
                    <div class="detail-item"><span class="detail-label">Status jadwal</span><div class="detail-value">{{ \App\Support\SirkelUi::label($handover->schedule_status) }}</div></div>
                </div>
                @if($handover->readyForPhysicalHandover() && !$asset->core_locked_at)
                    <details class="advanced-box mt-16">
                        <summary>Usulkan waktu lain</summary>
                        <form class="stack mt-16" method="post" action="{{ route('partner.requests.propose-time',$handover) }}">@csrf
                            <div class="field"><label>Tanggal & waktu usulan</label><input class="input" type="datetime-local" name="proposed_time" required></div>
                            <button class="btn">Kirim Usulan Jadwal</button>
                        </form>
                    </details>
                @endif
            </section>
        @endif
    </div>

    <div class="stack">
        @if($handover->status === 'pending')
            <section class="card action-panel">
                <div class="section-number">1</div>
                <h3>Terima permintaan ini?</h3>
                <p class="muted">Pastikan kategori, lokasi, dan kapasitas Anda sesuai.</p>
                <form method="post" action="{{ route('partner.requests.accept',$handover) }}">@csrf<button class="btn btn-primary btn-block">Terima Permintaan</button></form>
                <details class="advanced-box mt-16"><summary>Tidak dapat menerima</summary><form class="stack mt-16" method="post" action="{{ route('partner.requests.decline',$handover) }}">@csrf<textarea class="textarea" name="reason" required placeholder="Jelaskan singkat agar warga tahu mengapa perlu memilih mitra lain."></textarea><button class="btn btn-danger">Tolak Permintaan</button></form></details>
            </section>
        @elseif(in_array($handover->status, ['accepted','offered','offer_accepted'], true))
            @if($sale && $handover->status !== 'offer_accepted')
                <section class="card action-panel">
                    <div class="section-number">2</div>
                    <h3>{{ $handover->status === 'offered' ? 'Menunggu respons warga' : 'Buat penawaran nilai' }}</h3>
                    @if($offer)
                        <div class="offer-summary"><span class="metric-label">Penawaran aktif</span><strong>Rp{{ number_format((float)$offer->amount,0,',','.') }}</strong><small>Berlaku sampai {{ $offer->expires_at->format('d M Y H:i') }}</small></div>
                    @endif
                    <p class="muted">Pembayaran dilakukan langsung dengan warga.</p>
                    <form class="stack" method="post" action="{{ route('partner.requests.offer',$handover) }}">@csrf
                        <div class="field"><label>Nominal penawaran *</label><input class="input" type="number" min="0" name="amount" required></div>
                        <div class="field"><label>Catatan</label><textarea class="textarea" name="note" placeholder="Contoh: Nilai awal dapat berubah setelah pemeriksaan fisik."></textarea></div>
                        <div class="field"><label>Berlaku selama</label><select class="select" name="valid_hours"><option value="3">3 jam</option><option value="6" selected>6 jam</option><option value="12">12 jam</option><option value="24">24 jam</option><option value="48">48 jam</option></select></div>
                        <button class="btn {{ $handover->status === 'offered' ? '' : 'btn-primary' }}">{{ $handover->status === 'offered' ? 'Perbarui Penawaran' : 'Kirim Penawaran' }}</button>
                    </form>
                </section>
            @endif

            @if($readyToReceive)
                <section class="card action-panel">
                    <div class="section-number">3</div>
                    <h3>Konfirmasi saat barang benar-benar diterima</h3>
                    <p class="muted">Konfirmasi setelah barang fisik diterima. Barang kemudian masuk ke menu <strong>Barang Ditangani</strong>.</p>
                    <form class="stack" method="post" action="{{ route('partner.requests.receive',$handover) }}">@csrf
                        <div class="field"><label>Berat hasil penimbangan (kg) *</label><input class="input" type="number" min="0.001" step="0.001" name="verified_weight_kg" required><small>Berat ini menjadi dasar dampak terverifikasi.</small></div>
                        <button class="btn btn-primary">Barang Sudah Diterima Secara Fisik</button>
                    </form>
                </section>
            @endif
        @elseif(in_array($handover->status, ['declined','cancelled_by_user','cancelled_by_partner','offer_rejected','closed'], true))
            <section class="card"><h3>Permintaan sudah ditutup</h3><p class="muted">{{ \App\Support\SirkelUi::handoverStatus($handover->status) }}. Tidak ada tindakan lagi pada permintaan ini.</p></section>
        @endif

        <section class="card">
            <details>
                <summary><strong>Butuh melaporkan masalah?</strong></summary>
                <form class="stack mt-16" method="post" action="{{ route('partner.issues.store') }}">@csrf
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}"><input type="hidden" name="handover_request_id" value="{{ $handover->id }}">
                    <div class="field"><label>Jenis masalah</label><select class="select" name="category"><option value="user_unreachable">Warga tidak dapat dihubungi</option><option value="item_mismatch">Barang/kondisi tidak sesuai</option><option value="no_update">Tidak ada pembaruan</option><option value="behavior">Perilaku tidak sesuai</option><option value="other">Lainnya</option></select></div>
                    <div class="field"><label>Keterangan</label><textarea class="textarea" name="description" required></textarea></div>
                    <button class="btn">Kirim Laporan</button>
                </form>
            </details>
        </section>
    </div>
</div>
@endsection
