@extends('layouts.app')

@section('title', 'Beranda Mitra · SIRKEL')
@section('topbar', 'Beranda Mitra')

@section('content')
@php($adminActive = ($profile->admin_status ?? 'inactive') === 'active')
<div class="page-head">
    <div>
        <h2>{{ $profile->business_name }}</h2>
        <p>{{ collect([$profile->village, $profile->district, 'Surabaya'])->filter()->implode(', ') }}</p>
    </div>
    <div class="cluster">
        <span
            class="badge {{ $profile->verification_status === 'approved' ? 'success' : ($profile->verification_status === 'rejected' ? 'danger' : 'warning') }}">Verifikasi:
            {{ \App\Support\SirkelUi::label($profile->verification_status) }}</span>
        @if($profile->verification_status === 'approved')<span
            class="badge {{ $adminActive ? 'success' : 'danger' }}">Operasional:
        {{ \App\Support\SirkelUi::label($profile->admin_status ?? 'inactive') }}</span>@endif
        <a class="btn" href="{{ route('partner.onboarding.create') }}">Profil Mitra</a>
    </div>
</div>

@if($profile->verification_status !== 'approved')
    <div class="card">
        <h3>{{ $profile->verification_status === 'pending' ? 'Pengajuan sedang ditinjau' : 'Pengajuan perlu diperbarui' }}</h3>
        <div class="cluster">
            @foreach($profile->capabilities as $cap)<span
                class="badge {{ $cap->status === 'approved' ? 'success' : ($cap->status === 'rejected' ? 'danger' : 'warning') }}">{{ \App\Support\SirkelUi::label($cap->capability) }}
            · {{ \App\Support\SirkelUi::label($cap->status) }}</span>@endforeach
        </div>
    </div>
@else
    @if(!$adminActive)
        <div class="alert danger"><strong>Profil mitra sedang dinonaktifkan.</strong> Permintaan baru dan pengalihan baru tidak
            tersedia.</div>
    @endif

    <div class="kpi-grid">
        <div class="kpi"><span class="metric-label">Permintaan aktif</span><strong>{{ $requests->count() }}</strong></div>
        <div class="kpi"><span class="metric-label">Berat
                terverifikasi</span><strong>{{ number_format($impact['verified_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Berhasil
                diperbaiki</span><strong>{{ number_format($impact['repair_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Barang ditangani</span><strong>{{ $handledCount }}</strong></div>
    </div>

    <div class="cluster mb-16">
        <a class="btn btn-primary" href="{{ route('partner.requests.index') }}">Permintaan Masuk</a>
        <a class="btn" href="{{ route('partner.assets.index') }}">Barang Ditangani</a>
        @if($incomingTransfers->count())<span class="badge warning">{{ $incomingTransfers->count() }} pengalihan
        menunggu</span>@endif
    </div>

    <div class="two-col">
        <div class="stack">
            <div class="card">
                <div class="split">
                    <div>
                        <h3>Penerimaan Permintaan</h3>
                        <p class="muted mb-0">Jeda sementara jika Anda tidak ingin menerima permintaan baru.</p>
                    </div>
                    @if($adminActive)
                        <form method="post" action="{{ route('partner.availability') }}">@csrf<input type="hidden"
                                name="accepting_requests" value="{{ $profile->accepting_requests ? 0 : 1 }}"><button
                                class="btn {{ $profile->accepting_requests ? '' : 'btn-primary' }}">{{ $profile->accepting_requests ? 'Jeda Permintaan' : 'Mulai Terima Permintaan' }}</button>
                        </form>
                    @else
                        <button class="btn" type="button" disabled aria-disabled="true">Dinonaktifkan Admin</button>
                    @endif
                </div>
                <div class="stat-strip">
                    <div><span class="metric-label">Radius
                            penjemputan</span><strong>{{ number_format($profile->pickup_radius_km, 1) }} km</strong></div>
                    <div><span class="metric-label">Layanan
                            aktif</span><strong>{{ $profile->capabilities->where('status', 'approved')->count() }}</strong>
                    </div>
                    <div><span class="metric-label">Kategori
                            diterima</span><strong>{{ $profile->acceptedCategories->count() }}</strong></div>
                </div>
            </div>

            <div class="card">
                <div class="split">
                    <h3>Permintaan Warga</h3><a class="btn btn-sm" href="{{ route('partner.requests.index') }}">Lihat
                        semua</a>
                </div>
                @forelse($requests as $req)
                    <a class="list-row" href="{{ route('partner.requests.show', $req) }}">
                        <div><strong>{{ $req->asset->custom_item_name ?: $req->asset->category->name }}</strong>
                            <div class="text-sm muted">{{ $req->asset->passport_code }} ·
                                {{ \App\Support\SirkelUi::label($req->method) }} · {{ number_format($req->distance_km, 1) }} km
                            </div>
                        </div><span
                            class="badge {{ $req->outside_radius ? 'warning' : '' }}">{{ \App\Support\SirkelUi::handoverStatus($req->status) }}</span>
                    </a>
                @empty
                    <div class="empty">Belum ada permintaan.</div>
                @endforelse
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h3>Layanan Aktif</h3>
                <div class="cluster">@foreach($profile->capabilities->where('status', 'approved') as $cap)<span
                class="tag">{{ \App\Support\SirkelUi::label($cap->capability) }}</span>@endforeach</div>
            </div>

            @if($incomingTransfers->count())
                <div class="card">
                    <h3>Pengalihan Masuk</h3>
                    @foreach($incomingTransfers as $transfer)
                        <div class="transfer-incoming-card">
                            <div><strong>{{ $transfer->asset->passport_code }}</strong>
                                <div class="text-sm muted">Dari {{ $transfer->fromPartner->business_name }} ·
                                    {{ \App\Support\SirkelUi::label($transfer->required_capability) }}</div>@if($transfer->note)
                                    <div class="text-sm mt-8">{{ $transfer->note }}</div>@endif
                            </div>
                            <div class="cluster"><a class="btn btn-primary btn-sm"
                                    href="{{ route('partner.transfers.show', $transfer) }}">Tinjau</a></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
@endsection