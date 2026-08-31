@extends('layouts.app')

@section('title', 'Admin · SIRKEL')
@section('topbar', 'Ringkasan')

@section('content')
    <div class="page-head">
        <div>
            <h2>Ringkasan</h2>
        </div><a class="btn" href="{{ route('admin.settings.edit') }}">Pengaturan</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi"><span class="metric-label">Warga</span><strong>{{ $counts['users'] }}</strong></div>
        <div class="kpi"><span class="metric-label">Mitra</span><strong>{{ $counts['partners'] }} <small class="muted">·
                    {{ $counts['pending_partners'] }} menunggu</small></strong></div>
        <div class="kpi"><span class="metric-label">Barang</span><strong>{{ $counts['assets'] }}</strong></div>
        <div class="kpi"><span class="metric-label">Permintaan aktif</span><strong>{{ $counts['active_requests'] }}</strong>
        </div>
    </div>
    <div class="kpi-grid">
        <div class="kpi"><span class="metric-label">Berat
                terverifikasi</span><strong>{{ number_format($impact['verified_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Berhasil
                diperbaiki</span><strong>{{ number_format($impact['repair_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Pemulihan
                terkonfirmasi</span><strong>{{ number_format($impact['recovery_kg'], 2, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Laporan terbuka</span><strong>{{ $counts['open_issues'] }}</strong>
        </div>
    </div>

    <div class="two-col">
        <div class="card">
            <div class="split">
                <h3>Mitra terbaru</h3><a class="btn btn-sm" href="{{ route('admin.partners.index') }}">Lihat mitra</a>
            </div>
            @forelse($recentPartners as $p)
                <a class="list-row" href="{{ route('admin.partners.show', $p) }}">
                    <div><strong>{{ $p->business_name }}</strong>
                        <div class="text-sm muted">{{ $p->district }} · {{ $p->user->email }}</div>
                    </div><span
                        class="badge {{ $p->verification_status === 'approved' ? 'success' : ($p->verification_status === 'rejected' ? 'danger' : 'warning') }}">{{ \App\Support\SirkelUi::label($p->verification_status) }}</span>
                </a>
            @empty
                <div class="empty">Belum ada mitra.</div>
            @endforelse
        </div>
        <div class="card">
            <div class="split">
                <h3>Laporan</h3><a class="btn btn-sm" href="{{ route('admin.issues.index') }}">Lihat laporan</a>
            </div>
            @forelse($recentIssues as $i)
                <div class="list-row">
                    <div><strong>{{ \App\Support\SirkelUi::label($i->category) }}</strong>
                        <div class="text-sm muted">{{ $i->reporter->name }} · {{ $i->asset?->passport_code ?? 'Tanpa barang' }}
                        </div>
                    </div><span
                        class="badge {{ $i->status === 'open' ? 'warning' : '' }}">{{ \App\Support\SirkelUi::label($i->status) }}</span>
                </div>
            @empty
                <div class="empty">Tidak ada laporan.</div>
            @endforelse
        </div>
    </div>
@endsection