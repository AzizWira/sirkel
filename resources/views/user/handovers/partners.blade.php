@extends('layouts.app')

@section('title', 'Rekomendasi Mitra · SIRKEL')
@section('topbar', 'Pilih Mitra')

@section('content')
@php
    $recommendedPartner = $partners->first(fn($partner) => (bool) ($partner->is_recommended ?? false));
    $otherPartners = $partners->reject(fn($partner) => (bool) ($partner->is_recommended ?? false))->values();
@endphp

<div class="page-head">
    <div>
        <h2>Pilih Mitra</h2>
        <p>Mitra yang tampil sudah sesuai dengan kategori dan layanan yang dibutuhkan barang Anda.</p>
    </div>
</div>

<div class="stepper">
    <div class="step active"><span class="dot">✓</span>Barang</div>
    <div class="step active"><span class="dot">✓</span>Kondisi</div>
    <div class="step active"><span class="dot">✓</span>Rekomendasi</div>
    <div class="step active"><span class="dot">✓</span>Penyerahan</div>
    <div class="step active"><span class="dot">5</span>Mitra</div>
</div>

<div class="card flow-intent-card mt-16">
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">Tujuan penyerahan</span>
            <div class="detail-value">{{ \App\Support\SirkelUi::label($handover['handover_type']) }}</div>
        </div>
        <div class="detail-item"><span class="detail-label">Layanan yang dibutuhkan</span>
            <div class="detail-value">{{ \App\Support\SirkelUi::label($initialCapability) }}</div>
        </div>
    </div>
    @if($handover['handover_type'] === 'donation' && !in_array($asset->preliminary_path, ['REUSE', 'DONATION'], true))
        <div class="alert warning mt-16">Barang perlu melalui {{ \App\Support\SirkelUi::label($initialCapability) }}
            terlebih dahulu. Jika sudah layak, tujuan donasi tetap dapat dilanjutkan.</div>
    @endif
</div>

@if($recommendedPartner)
@php($recommendedWa = \App\Support\SirkelUi::whatsappUrl($recommendedPartner->phone, 'Halo ' . $recommendedPartner->business_name . ', saya ingin bertanya terkait penyerahan ' . $asset->passport_code . ' melalui SIRKEL.'))
<section class="partner-choice-section mt-16">
    <div class="section-head">
        <div>
            <div class="eyebrow">Direkomendasikan</div>
            <h3>Pilihan utama</h3>
        </div>
    </div>

    <article class="card partner-card recommended-partner-card {{ !$recommendedPartner->within_radius ? 'outside' : '' }}">
        <div class="partner-card-main">
            <div class="cluster partner-card-heading">
                <h3>{{ $recommendedPartner->business_name }}</h3>
                <span class="badge success">Direkomendasikan</span>
                <span class="badge">{{ \App\Support\SirkelUi::label($recommendedPartner->matched_capability) }}</span>
                @if($handover['method'] === 'pickup')
                    <span
                        class="badge {{ $recommendedPartner->within_radius ? 'success' : 'warning' }}">{{ $recommendedPartner->within_radius ? 'Dalam radius' : 'Di luar radius reguler' }}</span>
                @endif
            </div>
            <p class="muted partner-card-meta">{{ number_format($recommendedPartner->match_distance_km, 1) }} km dari
                titik Anda · radius penjemputan {{ $recommendedPartner->pickup_radius_km }} km</p>
            @if(($recommendedPartner->category_match_type ?? 'exact') === 'group')
                <div class="category-match-note">Cocok melalui cakupan umum
                    {{ $asset->category?->group?->name ?? 'kategori elektronik' }} yang diterima mitra.</div>
            @endif
            @if($recommendedPartner->recommendation_reason)
                <div class="recommendation-reason">
                    <span class="recommendation-reason-label">Mengapa direkomendasikan?</span>
                    <p>{{ $recommendedPartner->recommendation_reason }}</p>
                </div>
            @endif
            <div class="cluster partner-capabilities">
                @foreach($recommendedPartner->capabilities->where('status', 'approved') as $c)
                    <span class="tag">{{ \App\Support\SirkelUi::label($c->capability) }}</span>
                @endforeach
            </div>
            @if(!$recommendedPartner->within_radius && $handover['method'] === 'pickup')
                <p class="text-sm" style="color:var(--warning)">Di luar radius reguler. Mitra tetap dapat menerima atau
                    menolak permintaan.</p>
            @endif
        </div>
        <form class="partner-card-action" method="post" action="{{ route('user.handovers.create', $asset) }}">
            @csrf
            <input type="hidden" name="partner_profile_id" value="{{ $recommendedPartner->id }}">
            <input type="hidden" name="method" value="{{ $handover['method'] }}">
            <input type="hidden" name="handover_type" value="{{ $handover['handover_type'] }}">
            <label class="choice partner-final-ack">
                <input type="checkbox" name="ownership_acknowledgement" value="1" required>
                <span><strong>Saya setuju dengan penyerahan final.</strong><br><small class="muted">Setelah barang
                        diterima fisik oleh mitra, barang masuk proses penanganan SIRKEL dan tidak dikembalikan sebagai
                        layanan servis.</small></span>
            </label>
            <input type="hidden" name="latitude" value="{{ $handover['latitude'] }}">
            <input type="hidden" name="longitude" value="{{ $handover['longitude'] }}">
            <input type="hidden" name="address" value="{{ $handover['address'] ?? '' }}">
            <input type="hidden" name="district" value="{{ $handover['district'] }}">
            <input type="hidden" name="village" value="{{ $handover['village'] ?? '' }}">
            <input type="hidden" name="requested_date" value="{{ $handover['requested_date'] ?? '' }}">
            <input type="hidden" name="time_start" value="{{ $handover['time_start'] ?? '' }}">
            <input type="hidden" name="time_end" value="{{ $handover['time_end'] ?? '' }}">
            <div class="cluster">
                @if($recommendedWa)<a class="btn" href="{{ $recommendedWa }}" target="_blank" rel="noopener">Hubungi
                Mitra ↗</a>@endif
                <button class="btn btn-primary">Pilih Mitra Ini</button>
            </div>
        </form>
    </article>
</section>
@endif

@if($otherPartners->isNotEmpty())
<section class="partner-choice-section mt-24">
    <div class="section-head">
        <div>
            <div class="eyebrow">Opsi lainnya</div>
            <h3>Mitra lain yang sesuai</h3>
        </div>
    </div>
    <div class="stack">
        @foreach($otherPartners as $p)
        @php($partnerWa = \App\Support\SirkelUi::whatsappUrl($p->phone, 'Halo ' . $p->business_name . ', saya ingin bertanya terkait penyerahan ' . $asset->passport_code . ' melalui SIRKEL.'))
        <article class="card partner-card {{ !$p->within_radius ? 'outside' : '' }}">
            <div class="partner-card-main">
                <div class="cluster partner-card-heading">
                    <h3>{{ $p->business_name }}</h3>
                    <span class="badge success">Terverifikasi</span>
                    <span class="badge">{{ \App\Support\SirkelUi::label($p->matched_capability) }}</span>
                    @if($handover['method'] === 'pickup')
                        <span
                            class="badge {{ $p->within_radius ? 'success' : 'warning' }}">{{ $p->within_radius ? 'Dalam radius' : 'Di luar radius reguler' }}</span>
                    @endif
                </div>
                <p class="muted partner-card-meta">{{ number_format($p->match_distance_km, 1) }} km dari titik Anda ·
                    radius penjemputan {{ $p->pickup_radius_km }} km</p>
                @if(($p->category_match_type ?? 'exact') === 'group')
                    <div class="category-match-note">Cocok melalui cakupan umum
                        {{ $asset->category?->group?->name ?? 'kategori elektronik' }}.</div>
                @endif
                <div class="cluster partner-capabilities">
                    @foreach($p->capabilities->where('status', 'approved') as $c)
                        <span class="tag">{{ \App\Support\SirkelUi::label($c->capability) }}</span>
                    @endforeach
                </div>
                @if(!$p->within_radius && $handover['method'] === 'pickup')
                    <p class="text-sm" style="color:var(--warning)">Di luar radius reguler. Mitra tetap dapat menerima atau
                        menolak permintaan.</p>
                @endif
            </div>
            <form class="partner-card-action" method="post" action="{{ route('user.handovers.create', $asset) }}">
                @csrf
                <input type="hidden" name="partner_profile_id" value="{{ $p->id }}">
                <input type="hidden" name="method" value="{{ $handover['method'] }}">
                <input type="hidden" name="handover_type" value="{{ $handover['handover_type'] }}">
                <label class="choice partner-final-ack">
                    <input type="checkbox" name="ownership_acknowledgement" value="1" required>
                    <span><strong>Saya setuju dengan penyerahan final.</strong><br><small class="muted">Setelah barang
                            diterima fisik oleh mitra, barang masuk proses penanganan SIRKEL dan tidak dikembalikan
                            sebagai layanan servis.</small></span>
                </label>
                <input type="hidden" name="latitude" value="{{ $handover['latitude'] }}">
                <input type="hidden" name="longitude" value="{{ $handover['longitude'] }}">
                <input type="hidden" name="address" value="{{ $handover['address'] ?? '' }}">
                <input type="hidden" name="district" value="{{ $handover['district'] }}">
                <input type="hidden" name="village" value="{{ $handover['village'] ?? '' }}">
                <input type="hidden" name="requested_date" value="{{ $handover['requested_date'] ?? '' }}">
                <input type="hidden" name="time_start" value="{{ $handover['time_start'] ?? '' }}">
                <input type="hidden" name="time_end" value="{{ $handover['time_end'] ?? '' }}">
                <div class="cluster">
                    @if($partnerWa)<a class="btn" href="{{ $partnerWa }}" target="_blank" rel="noopener">Hubungi Mitra
                    ↗</a>@endif
                    <button class="btn">Pilih Mitra Ini</button>
                </div>
            </form>
        </article>
        @endforeach
    </div>
</section>
@endif

@if($partners->isEmpty())
<div class="card empty mt-16">
    <h3>Belum ada mitra yang cocok</h3>
    @if($matchingHelpIssue)
    @php($helpRequest = $matchingHelpIssue->request)
        @if($matchingHelpIssue->status === 'in_review' && $helpRequest && $helpRequest->status === 'pending')
            <p>SIRKEL sedang menghubungi <strong>{{ $helpRequest->partner->business_name ?? 'calon mitra' }}</strong> untuk
                meninjau barang ini. Anda akan mendapat pemberitahuan setelah mitra merespons.</p>
            <span class="badge warning">Menunggu tanggapan mitra</span>
        @else
            <p>Permintaan bantuan Anda sedang ditangani. SIRKEL akan mencarikan mitra yang memiliki layanan sesuai dan memberi
                kabar saat ada perkembangan.</p>
            <span class="badge warning">Sedang dicarikan mitra</span>
        @endif
        <div class="matching-help-box mt-16">
            <a class="btn" href="{{ route('user.handovers.match.form', $asset) }}">Ubah Cara Penyerahan</a>
        </div>
    @else
    <p>Barang tetap tersimpan. Anda dapat mencoba metode penyerahan lain atau meminta bantuan SIRKEL untuk mencarikan
        mitra.</p>
    <div class="matching-help-box">
        <a class="btn" href="{{ route('user.handovers.match.form', $asset) }}">Ubah Cara Penyerahan</a>
        <form class="stack" method="post" action="{{ route('user.issues.store') }}">
            @csrf
            <input type="hidden" name="asset_id" value="{{ $asset->id }}">
            <input type="hidden" name="category" value="matching_help">
            <input type="hidden" name="description"
                value="Belum ada mitra yang cocok untuk {{ $asset->custom_item_name ?: $asset->category->name }} ({{ $asset->category->name }}) dengan layanan {{ \App\Support\SirkelUi::label($initialCapability) }} dan metode {{ \App\Support\SirkelUi::label($handover['method']) }}. Mohon bantu carikan mitra yang sesuai.">
            <label class="choice partner-final-ack">
                <input type="checkbox" name="matching_help_authorization" value="1" required>
                <span><strong>Saya setuju SIRKEL membantu meneruskan permintaan ini ke mitra yang dinilai
                        sesuai.</strong><br><small class="muted">Mitra tetap akan meninjau dan dapat menerima atau
                        menolak. Jika barang kemudian diserahkan dan diterima fisik oleh mitra, barang masuk proses
                        penanganan SIRKEL dan tidak dikembalikan sebagai layanan servis.</small></span>
            </label>
            <button class="btn btn-primary">Minta Bantuan SIRKEL</button>
        </form>
    </div>
    @endif
</div>
@endif
@endsection