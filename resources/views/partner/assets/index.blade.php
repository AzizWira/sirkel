@extends('layouts.app')

@section('title', 'Barang Ditangani · SIRKEL')
@section('topbar', 'Barang Ditangani')

@section('content')
    <div class="page-head">
        <div>
            <h2>Barang Ditangani</h2>
        </div>
    </div>

    <div class="subnav mt-16">
        <a class="{{ $scope === 'active' ? 'active' : '' }}" href="{{ route('partner.assets.index') }}">Perlu Ditangani</a>
        <a class="{{ $scope === 'history' ? 'active' : '' }}"
            href="{{ route('partner.assets.index', ['scope' => 'history']) }}">Riwayat</a>
    </div>

    <div class="card">
        @forelse($assets as $asset)
            @php
                $custody = $asset->custody->where('partner_profile_id', $profile->id)->sortByDesc('received_at')->first();
                $lastAssessment = $asset->assessments->where('assessment_type', 'partner')->where('assessor_user_id', $profile->user_id)->sortByDesc('id')->first();
                $pendingTransfer = $asset->transfers->where('from_partner_id', $profile->id)->where('status', 'pending')->sortByDesc('id')->first();
                $needsTransfer = in_array($asset->status, ['needs_transfer', 'transferred'], true) || \App\Support\SirkelUi::isTransferDecision($lastAssessment?->result_path);
            @endphp
            <a class="handling-row" href="{{ route('partner.assets.show', $asset) }}">
                <div class="handling-main">
                    <div class="cluster">
                        <strong>{{ $asset->custom_item_name ?: $asset->category->name }}</strong>
                        @if($pendingTransfer)
                            <span class="badge warning">Menunggu
                                {{ $pendingTransfer->toPartner?->business_name ?? 'Mitra Tujuan' }}</span>
                        @elseif($needsTransfer)
                            <span class="badge warning">Perlu Layanan Lanjutan</span>
                        @elseif($asset->final_path)
                            <span
                                class="badge {{ \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path) ? 'success' : 'warning' }}">{{ \App\Support\SirkelUi::label($asset->final_path) }}</span>
                        @elseif($asset->status === 'in_processing')
                            <span class="badge">Sedang Diproses di Mitra Ini</span>
                        @else
                            <span class="badge">Menunggu Pemeriksaan</span>
                        @endif
                    </div>
                    <div class="text-sm muted">
                        {{ $asset->passport_code }} · {{ $asset->category->name }}
                        @if($asset->verified_weight_kg !== null)
                            · {{ number_format((float) $asset->verified_weight_kg, 3, ',', '.') }} kg
                        @endif
                    </div>
                </div>
                <div class="handling-meta">
                    <span class="text-sm muted">{{ $custody?->received_at?->format('d M Y H:i') }}</span>
                    <span class="btn btn-sm">Buka</span>
                </div>
            </a>
        @empty
            <div class="empty">
                @if($scope === 'active')
                    <strong>Tidak ada barang yang perlu ditangani.</strong>
                    <div class="text-sm muted mt-16">Barang akan muncul setelah diterima dari warga atau mitra lain.</div>
                @else
                    Belum ada riwayat penanganan.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-16">{{ $assets->links() }}</div>
@endsection