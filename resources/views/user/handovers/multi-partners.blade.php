@extends('layouts.app')
@section('title', 'Rencana Mitra Multi-Barang · SIRKEL')
@section('topbar', 'Rencana Mitra')

@section('content')
    <div class="page-head multi-partner-page-head">
        <div class="multi-partner-page-copy">
            <span class="eyebrow">Rencana penyerahan · {{ $items->count() }} kelompok</span>
            <h2>Pilih mitra dengan sekali konfirmasi</h2>
            <p>SIRKEL membandingkan kecocokan setiap barang. Jika satu mitra dapat menangani semuanya, Anda dapat memilihnya sekaligus. Barang yang belum memiliki mitra tetap dapat dikirim dan akan masuk antrean bantuan SIRKEL.</p>
        </div>
        <a class="btn multi-partner-change-button" href="{{ route('user.intake.handover.form', $session) }}">Ubah Cara Penyerahan</a>
    </div>

    @if ($commonPartners->isNotEmpty())
        <section class="card stack mb-16">
            <div>
                <span class="eyebrow">Paling praktis</span>
                <h3>Satu mitra dapat menerima semua {{ $items->count() }} kelompok</h3>
                <p class="muted">Anda tetap dapat memilih mitra berbeda per barang di bagian bawah.</p>
            </div>

            @foreach ($commonPartners as $partner)
                <div class="card partner-card recommended-partner-card">
                    <div class="partner-card-main">
                        <div class="cluster">
                            <h3>{{ $partner->business_name }}</h3>
                            <span class="badge success">Cocok untuk semua</span>
                        </div>
                        <p class="muted">{{ $partner->district }}, Surabaya · radius {{ $partner->pickup_radius_km }} km</p>
                    </div>

                    <div class="partner-card-action cluster">
                        @if ($commonWhatsappByPartner[$partner->public_id] ?? null)
                            <a class="btn" href="{{ $commonWhatsappByPartner[$partner->public_id] }}" target="_blank"
                                rel="noopener">Hubungi Mitra ↗</a>
                        @endif

                        <form method="post" action="{{ route('user.intake.handover.create', $session) }}">
                            @csrf
                            <input type="hidden" name="common_partner" value="{{ $partner->public_id }}">
                            <input type="hidden" name="ownership_acknowledgement" value="1">
                            <button class="btn btn-primary"
                                data-confirm="Kirim seluruh barang dalam rencana ini ke mitra tersebut?">
                                Pilih Mitra Ini untuk Semua
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <form class="stack" method="post" action="{{ route('user.intake.handover.create', $session) }}">
        @csrf

        @if($assistanceCount > 0)
            <div class="alert warning">
                <strong>Pengajuan tetap dapat dilanjutkan.</strong><br>
                {{ $matchedCount }} kelompok akan dikirim ke mitra yang Anda pilih dan {{ $assistanceCount }} kelompok akan berstatus <strong>Butuh Bantuan SIRKEL</strong>. Barang tersebut masuk antrean Admin untuk penentuan mitra dan perkembangannya tetap terlihat oleh Anda.
            </div>
        @endif

        <section class="stack">
            <div>
                <span class="eyebrow">Pilihan per kelompok</span>
                <h3>Atur mitra masing-masing barang</h3>
            </div>

            @foreach ($items as $item)
                <div class="card stack">
                    <div class="split">
                        <div>
                            <strong>
                                {{ $item->asset->custom_item_name ?: $item->asset->category?->name }}{{ $item->asset->quantity > 1 ? ' ×' . $item->asset->quantity : '' }}
                            </strong>
                            <div class="text-sm muted">
                                {{ \App\Support\SirkelUi::label($context['handover_types'][$item->asset->public_id] ?? null) }}
                                · {{ \App\Support\SirkelUi::label($item->asset->preliminary_path) }}
                            </div>
                        </div>
                        <span class="badge">{{ $partnersByAsset[$item->asset->public_id]->count() }} pilihan</span>
                    </div>

                    @if ($partnersByAsset[$item->asset->public_id]->isEmpty())
                        <input type="hidden" name="assist[{{ $item->asset->public_id }}]" value="1">
                        <div class="matching-assistance-outcome">
                            <span class="badge warning">Butuh Bantuan SIRKEL</span>
                            <div>
                                <strong>Belum ada mitra yang cocok untuk kelompok ini.</strong>
                                <p class="muted mb-0">Saat seluruh pengajuan dikirim, barang ini tidak disimpan sebagai pilihan mitra kosong. SIRKEL membuat antrean bantuan khusus agar Admin dapat menentukan mitra yang sesuai.</p>
                            </div>
                        </div>
                    @else
                        <div class="stack">
                            @foreach ($partnersByAsset[$item->asset->public_id] as $partner)
                                <label class="choice partner-plan-choice">
                                    <input type="radio" name="partners[{{ $item->asset->public_id }}]" value="{{ $partner->public_id }}"
                                        required>
                                    <span style="flex:1">
                                        <strong>{{ $partner->business_name }}</strong><br>
                                        <small class="muted">
                                            {{ number_format((float) $partner->match_distance_km, 1, ',', '.') }} km
                                            · {{ \App\Support\SirkelUi::label($partner->matched_capability) }}
                                        </small>
                                    </span>

                                    @if ($partnerWhatsappByAsset[$item->asset->public_id][$partner->public_id] ?? null)
                                        <a class="btn btn-sm"
                                            href="{{ $partnerWhatsappByAsset[$item->asset->public_id][$partner->public_id] }}"
                                            target="_blank" rel="noopener" onclick="event.stopPropagation()">Hubungi ↗</a>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>

        <label class="choice">
            <input type="checkbox" name="ownership_acknowledgement" value="1" required>
            <span>
                <strong>Saya setuju dengan rencana penyerahan ini.</strong><br>
                <small class="muted">Setelah barang diterima fisik oleh mitra, barang masuk proses penanganan
                    SIRKEL.</small>
            </span>
        </label>

        <button class="btn btn-primary" type="submit">Kirim Semua Permintaan</button>
    </form>
@endsection
