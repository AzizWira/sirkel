@extends('layouts.app')

@section('title', 'Permintaan Masuk · SIRKEL')
@section('topbar', 'Permintaan Masuk')

@section('content')
<div class="page-head">
    <div><h2>Permintaan Masuk</h2></div>
</div>

<div class="request-inbox-summary">
    <div class="card request-inbox-stat">
        <span class="metric-label">Dari warga</span>
        <strong>{{ $requests->total() }}</strong>
        <span class="text-sm muted">Permintaan penyerahan dari warga.</span>
    </div>
    <div class="card request-inbox-stat {{ $incomingTransfers->count() ? 'attention' : '' }}">
        <span class="metric-label">Dari mitra lain</span>
        <strong>{{ $incomingTransfers->count() }}</strong>
        <span class="text-sm muted">Pengalihan dari mitra lain.</span>
    </div>
</div>

<div class="subnav mt-16">
    <a class="{{ $scope === 'active' ? 'active' : '' }}" href="{{ route('partner.requests.index') }}">Perlu Diproses</a>
    <a class="{{ $scope === 'history' ? 'active' : '' }}" href="{{ route('partner.requests.index', ['scope' => 'history']) }}">Riwayat</a>
</div>

@if($incomingTransfers->count())
<div class="card inbox-section">
    <div class="section-head">
        <div>
            <span class="eyebrow">{{ $scope === 'active' ? 'BUTUH RESPONS ANDA' : 'RIWAYAT PENGALIHAN' }}</span>
            <h3>{{ $scope === 'active' ? 'Pengalihan dari Mitra Lain' : 'Pengalihan yang Pernah Masuk' }}</h3>
            <p class="muted mb-0">
                @if($scope === 'active')
                    Tinjau pengalihan dan konfirmasi setelah barang fisik diterima.
                @else
                    Pengalihan yang pernah masuk ke mitra Anda.
                @endif
            </p>
        </div>
    </div>

    <div class="transfer-inbox-list">
        @foreach($incomingTransfers as $transfer)
            @php
                $sourceAssessment = $transfer->asset->assessments
                    ->where('assessment_type', 'partner')
                    ->where('assessor_user_id', $transfer->fromPartner?->user_id)
                    ->sortByDesc('id')
                    ->first();
            @endphp
            <a class="transfer-inbox-row" href="{{ route('partner.transfers.show', $transfer) }}">
                <div class="transfer-inbox-main">
                    <div class="cluster">
                        <strong>{{ $transfer->asset->custom_item_name ?: $transfer->asset->category->name }}</strong>
                        <span class="badge {{ $transfer->status === 'pending' ? 'warning' : ($transfer->status === 'received' ? 'success' : '') }}">
                            {{ $transfer->status === 'pending' ? 'Menunggu Respons Anda' : \App\Support\SirkelUi::label($transfer->status) }}
                        </span>
                    </div>
                    <div class="text-sm muted mt-4">{{ $transfer->asset->passport_code }} · Dari {{ $transfer->fromPartner?->business_name ?? 'Mitra sebelumnya' }}</div>
                    <div class="text-sm mt-8"><strong>Layanan yang dibutuhkan:</strong> {{ \App\Support\SirkelUi::label($transfer->required_capability) }}</div>
                    @if($sourceAssessment)
                        <div class="text-sm muted mt-4">Hasil pemeriksaan sebelumnya: {{ \App\Support\SirkelUi::label($sourceAssessment->result_path) }}</div>
                    @endif
                </div>
                <div class="transfer-inbox-action">
                    <span class="text-sm muted">{{ $transfer->requested_at?->format('d M Y H:i') }}</span>
                    <span class="btn btn-sm {{ $transfer->status === 'pending' ? 'btn-primary' : '' }}">{{ $transfer->status === 'pending' ? 'Tinjau Pengalihan' : 'Lihat' }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="card inbox-section">
    <div class="section-head">
        <div>
            <span class="eyebrow">{{ $scope === 'active' ? 'DARI WARGA' : 'RIWAYAT PENYERAHAN WARGA' }}</span>
            <h3>{{ $scope === 'active' ? 'Permintaan Penyerahan dari Warga' : 'Penyerahan yang Sudah Ditutup' }}</h3>
            <p class="muted mb-0">
                @if($scope === 'active')
                    Tinjau dan respons permintaan warga.
                @else
                    Penyerahan warga yang sudah ditutup.
                @endif
            </p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="mobile-table mobile-table-6">
            <thead>
                <tr>
                    <th>Barang</th>
                    <th>Penyerahan</th>
                    <th>Lokasi</th>
                    <th>Status</th>
                    <th>Jadwal</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $handover)
                @php
                    $hasActiveCustody = $handover->asset->custody
                        ->where('partner_profile_id', $profile->id)
                        ->whereNull('released_at')
                        ->isNotEmpty();
                @endphp
                <tr>
                    <td>
                        <strong>{{ $handover->asset->custom_item_name ?: $handover->asset->category->name }}</strong>
                        <div class="text-sm muted">{{ $handover->asset->passport_code }}</div>
                    </td>
                    <td>
                        {{ \App\Support\SirkelUi::label($handover->method) }}
                        <div class="text-sm muted">{{ \App\Support\SirkelUi::label($handover->effectiveHandoverType()) }}</div>
                    </td>
                    <td>
                        @if($handover->method === 'pickup')
                            {{ $handover->pickup_village ? $handover->pickup_village.', ' : '' }}{{ $handover->pickup_district }}
                            <div class="text-sm muted">{{ number_format((float)$handover->distance_km, 1) }} km</div>
                            @if($handover->outside_radius)<span class="badge warning">Di luar radius</span>@endif
                        @else
                            Warga mengantar ke lokasi mitra
                            <div class="text-sm muted">Jarak perkiraan {{ number_format((float)$handover->distance_km, 1) }} km</div>
                        @endif
                    </td>
                    <td><span class="badge {{ $handover->status === 'completed' ? 'success' : '' }}">{{ \App\Support\SirkelUi::handoverStatus($handover->status) }}</span></td>
                    <td>
                        {{ $handover->requested_date?->format('d M Y') ?? 'Belum ditentukan' }}
                        @if($handover->requested_time_start)
                            <div class="text-sm muted">{{ substr($handover->requested_time_start,0,5) }}{{ $handover->requested_time_end ? ' – '.substr($handover->requested_time_end,0,5) : '' }}</div>
                        @endif
                    </td>
                    <td>
                        @if($handover->status === 'completed' && $hasActiveCustody)
                            <a class="btn btn-sm btn-primary" href="{{ route('partner.assets.show', $handover->asset) }}">Tangani Barang</a>
                        @else
                            <a class="btn btn-sm" href="{{ route('partner.requests.show', $handover) }}">Buka</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">
                    @if($scope === 'active')
                        Tidak ada permintaan warga yang perlu diproses.
                    @else
                        Belum ada riwayat penyerahan warga.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-16">{{ $requests->links() }}</div>
@endsection
