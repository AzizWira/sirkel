@extends('layouts.app')

@section('title', 'Alihkan Barang · SIRKEL')
@section('topbar', 'Alihkan Barang')

@section('content')
    @php
        $recommendedPartner = $partners->first(fn($partner) => (bool) ($partner->is_recommended ?? false));
        $otherPartners = $partners->reject(fn($partner) => (bool) ($partner->is_recommended ?? false))->values();
    @endphp

    <div class="page-head">
        <div>
            <div class="cluster"><span class="badge">{{ $asset->passport_code }}</span><span
                    class="badge warning">{{ $requiredCapabilityLabel }}</span></div>
            <h2 style="margin-top:8px">Pilih Mitra Lanjutan</h2>
            <p>Butuh layanan <strong>{{ $requiredCapabilityLabel }}</strong>.</p>
        </div>
        <a class="btn" href="{{ route('partner.assets.show', $asset) }}">Kembali</a>
    </div>

    <div class="next-action-card warning-state">
        <div class="next-action-kicker">Layanan lanjutan</div>
        <h3>{{ \App\Support\SirkelUi::label($assessment?->result_path) }}</h3>
        <p>Barang tetap menjadi tanggung jawab mitra Anda sampai mitra tujuan mengonfirmasi penerimaan.</p>
    </div>

    <form class="card stack mt-16" method="post" action="{{ route('partner.transfers.store', $asset) }}">
        @csrf
        <div class="field">
            <label>Mitra tujuan *</label>

            @if($recommendedPartner)
                <div class="eyebrow mt-8">Direkomendasikan</div>
                <div class="choice-list mt-8">
                    <label class="choice recommended-choice">
                        <input type="radio" name="to_partner_id" value="{{ $recommendedPartner->id }}" required {{ (string) old('to_partner_id') === (string) $recommendedPartner->id ? 'checked' : '' }}>
                        <span>
                            <span class="cluster">
                                <strong>{{ $recommendedPartner->business_name }}</strong>
                                <span class="badge success">Direkomendasikan</span>
                            </span>
                            <small>{{ collect([$recommendedPartner->village, $recommendedPartner->district, 'Surabaya'])->filter()->implode(', ') }}</small>
                            <small>{{ number_format((float) $recommendedPartner->transfer_distance_km, 1) }} km dari lokasi mitra
                                Anda · {{ $requiredCapabilityLabel }}</small>
                            @if(($recommendedPartner->category_match_type ?? 'exact') === 'group')<small>Cocok melalui cakupan
                            umum kelompok kategori barang.</small>@endif
                            @if($recommendedPartner->recommendation_reason)
                                <small><strong>{{ $recommendedPartner->recommendation_reason }}</strong></small>
                            @endif
                        </span>
                    </label>
                </div>
            @endif

            @if($otherPartners->isNotEmpty())
                <div class="eyebrow mt-16">Opsi lainnya</div>
                <div class="choice-list mt-8">
                    @foreach($otherPartners as $p)
                        <label class="choice">
                            <input type="radio" name="to_partner_id" value="{{ $p->id }}" required {{ (string) old('to_partner_id') === (string) $p->id ? 'checked' : '' }}>
                            <span>
                                <strong>{{ $p->business_name }}</strong>
                                <small>{{ collect([$p->village, $p->district, 'Surabaya'])->filter()->implode(', ') }}</small>
                                <small>{{ number_format((float) $p->transfer_distance_km, 1) }} km dari lokasi mitra Anda ·
                                    {{ $requiredCapabilityLabel }}</small>
                                @if(($p->category_match_type ?? 'exact') === 'group')<small>Cocok melalui cakupan umum kelompok
                                kategori barang.</small>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            @if($partners->isEmpty())
                <div class="empty">
                    <strong>Belum ada mitra {{ $requiredCapabilityLabel }} yang cocok.</strong>
                    <div class="text-sm muted mt-16">Barang tetap berada di mitra Anda. Coba lagi saat mitra yang sesuai
                        tersedia.</div>
                </div>
            @endif
        </div>

        <div class="field">
            <label>Catatan untuk mitra tujuan *</label>
            <textarea class="textarea" name="note" required maxlength="700"
                placeholder="Jelaskan hasil pemeriksaan dan alasan barang membutuhkan layanan {{ $requiredCapabilityLabel }}.">{{ old('note') }}</textarea>
            <small>Jangan masukkan data pribadi warga yang tidak diperlukan.</small>
        </div>

        <button class="btn btn-primary" {{ $partners->isEmpty() ? 'disabled' : '' }}>Ajukan Pengalihan</button>
    </form>
@endsection