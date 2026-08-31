@extends('layouts.app')

@section('title', 'Beranda · SIRKEL')
@section('topbar', 'Beranda')

@section('content')
    <div class="page-head">
        <div>
            <h2>Halo, {{ auth()->user()->name }}</h2>
            <p>Ada elektronik yang ingin Anda cek atau serahkan?</p>
        </div>
        <a class="btn btn-primary" href="{{ route('user.assets.create') }}">+ Daftarkan Elektronik</a>
    </div>

    @if($assets->isEmpty())
        <div class="card mb-16">
            <h3>Belum ada barang</h3>
            <p class="muted">Daftarkan elektronik pertama Anda untuk mulai cek kondisi dan mencari mitra.</p>
            <a class="btn btn-primary" href="{{ route('user.assets.create') }}">Daftarkan Elektronik</a>
        </div>
    @endif

    <div class="kpi-grid">
        <div class="kpi"><span class="metric-label">Barang
                terverifikasi</span><strong>{{ $impact['verified_assets'] }}</strong></div>
        <div class="kpi"><span class="metric-label">Berat
                terverifikasi</span><strong>{{ number_format($impact['verified_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Berhasil
                diperbaiki</span><strong>{{ number_format($impact['repair_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Pemulihan
                terkonfirmasi</span><strong>{{ number_format($impact['recovery_kg'], 2, ',', '.') }} kg</strong></div>
    </div>

    <div class="card">
        <div class="split">
            <h3>Elektronik Anda</h3><a class="btn btn-sm" href="{{ route('user.assets.index') }}">Lihat semua</a>
        </div>
        <div class="divider"></div>
        @forelse($assets as $asset)
            <a class="flow-line" href="{{ route('user.assets.show', $asset) }}">
                <div class="flow-num">{{ strtoupper(substr($asset->category->name, 0, 1)) }}</div>
                <div style="flex:1"><strong>{{ $asset->custom_item_name ?: $asset->category->name }}</strong>
                    <div class="text-sm muted">{{ $asset->passport_code }} ·
                        {{ \App\Support\SirkelUi::assetProgress($asset->status, $asset->final_path) }}</div>
                </div>
                @if($asset->preliminary_path)<span
                class="badge">{{ \App\Support\SirkelUi::label($asset->preliminary_path) }}</span>@endif
            </a>
        @empty
            <div class="empty">Belum ada barang.</div>
        @endforelse
    </div>
@endsection