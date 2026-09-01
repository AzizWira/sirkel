@extends('layouts.app')
@section('title','Review Pengajuan · SIRKEL')
@section('topbar','Review Pengajuan')
@section('content')
@php
    $actionableCount = $actionableItems->count();
    $allCount = $items->count();
@endphp
<div class="page-head">
    <div>
        <h2>{{ $session->isBulk() ? 'Review Bulk AI' : 'Review Pemeriksaan' }}</h2>
        <p>{{ $allCount }} kelompok sudah selesai dicek. Tinjau rekomendasi awal dan lanjutkan hanya barang yang memang belum masuk proses penyerahan.</p>
    </div>
    <div class="cluster">
        <a class="btn" href="{{ route('user.cart.index') }}">Keranjang</a>
        @if($session->status === \App\Models\IntakeSession::STATUS_REVIEW && $actionableCount > 1)
            <a class="btn btn-primary" href="{{ route('user.intake.handover.form', $session) }}">Atur Penyerahan Semua</a>
        @elseif($session->status !== \App\Models\IntakeSession::STATUS_REVIEW || $actionableCount === 0)
            <span class="badge success">Proses ini sudah dilanjutkan</span>
        @endif
    </div>
</div>

@if($actionableCount > 0)
    <div class="hint-box mb-16">
        <strong>Lokasi dan jadwal cukup diisi satu kali.</strong>
        SIRKEL akan mencari mitra untuk {{ $actionableCount }} kelompok yang masih perlu penyerahan. Barang yang sudah memiliki permintaan atau sudah selesai tidak akan dimasukkan lagi.
    </div>
@else
    <div class="alert success mb-16">
        Tidak ada barang dari pemeriksaan ini yang perlu dilanjutkan lagi. Buka <a href="{{ route('user.activity') }}"><strong>Aktivitas</strong></a> atau detail barang untuk melihat perkembangan terbaru.
    </div>
@endif

<div class="review-grid">
@foreach($items as $item)
    @php
        $asset = $item->asset;
        $assessment = $asset?->assessments?->where('assessment_type', 'user')->sortByDesc('id')->first();
        $state = $reviewStates[$item->id] ?? \App\Services\IntakeSessionStateService::ITEM_UNAVAILABLE;
    @endphp
    <div class="card stack review-item-card">
        <div class="review-item-head">
            <div class="review-item-identity">
                <strong>{{ $asset->custom_item_name ?: $asset->category?->name }}{{ $asset->quantity > 1 ? ' ×'.$asset->quantity : '' }}</strong>
                <div class="muted text-sm review-item-passport">{{ $asset->passport_code }}</div>
            </div>
            @if($state === \App\Services\IntakeSessionStateService::ITEM_ACTIONABLE)
                <span class="badge warning review-item-status">Siap dilanjutkan</span>
            @elseif($state === \App\Services\IntakeSessionStateService::ITEM_FINISHED)
                <span class="badge success review-item-status">Penanganan selesai</span>
            @elseif($state === \App\Services\IntakeSessionStateService::ITEM_IN_PROGRESS)
                <span class="badge success review-item-status">Sudah dilanjutkan</span>
            @else
                <span class="badge review-item-status">Belum siap</span>
            @endif
        </div>

        <p class="mb-0">{{ $assessment?->summary ?: 'Rekomendasi awal sudah tersedia.' }}</p>
        <div class="hint-box"><strong>Rekomendasi awal:</strong> {{ \App\Support\SirkelUi::label($asset->preliminary_path) }}</div>

        @if($state === \App\Services\IntakeSessionStateService::ITEM_ACTIONABLE)
            <a class="btn" href="{{ route('user.handovers.match.form', $asset) }}">Atur Barang Ini Saja</a>
        @else
            <a class="btn" href="{{ route('user.assets.show', $asset) }}">Lihat Perkembangan Barang</a>
        @endif
    </div>
@endforeach
</div>
@endsection
