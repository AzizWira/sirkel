@extends('layouts.app')

@section('title','AI & Biaya · SIRKEL')

@section('topbar','AI & Biaya')

@section('content')
<div class="page-head">
    <div>
        <h2>Pemakaian AI & Kontrol Biaya</h2>
        <p>Pantau penggunaan, biaya, dan status layanan AI.</p>
    </div>
    <div class="cluster">
        <a class="btn" href="{{ route('admin.ai-quota.index') }}">Top Up Kuota AI</a>
        <a class="btn" href="{{ route('admin.settings.edit') }}">Atur Anggaran</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi"><span class="metric-label">Biaya bulan ini</span><strong>${{ number_format($totalCost,4) }}</strong></div>
    <div class="kpi"><span class="metric-label">Anggaran</span><strong>${{ number_format($budget,2) }}</strong></div>
    <div class="kpi"><span class="metric-label">Total pemakaian</span><strong>{{ $calls }}</strong></div>
    <div class="kpi"><span class="metric-label">Gagal</span><strong>{{ $failed }}</strong></div>
</div>

<div class="card">
    <div class="split">
        <div>
            <h3>Penggunaan Anggaran</h3>
            <p class="muted mb-0">{{ number_format($budgetPct,1) }}% dari anggaran bulan berjalan</p>
        </div>
        <strong>{{ number_format($budgetPct,1) }}%</strong>
    </div>
    <div style="height:10px;background:var(--surface-2);border-radius:99px;overflow:hidden;margin-top:12px">
        <div style="height:100%;width:{{ $budgetPct }}%;background:var(--primary)"></div>
    </div>
</div>

@if(session('ai_narrative'))
<div class="card ai-panel mt-16">
    <div class="ai-label">Ringkasan Dampak dari AI</div>
    <p class="mb-0">{{ session('ai_narrative') }}</p>
</div>
@endif

<div class="two-col mt-16">
    <div class="card">
        <div class="split">
            <h3>Pemakaian berdasarkan Fitur</h3>
            <form method="post" action="{{ route('admin.ai.narrative') }}">
                @csrf
                <button class="btn btn-sm">Buat Ringkasan Dampak</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="mobile-table mobile-table-5">
                <thead>
                    <tr><th>Fitur</th><th>Model AI</th><th>Pemakaian</th><th>Token tersimpan</th><th>Biaya</th></tr>
                </thead>
                <tbody>
@forelse($usage as $u)
                    <tr>
                        <td>{{ \App\Support\SirkelUi::label($u->feature) }}</td>
                        <td><span class="code">{{ $u->model }}</span></td>
                        <td>{{ $u->calls }}</td>
                        <td>{{ number_format($u->cached_input_tokens) }}</td>
                        <td>${{ number_format($u->cost,5) }}</td>
                    </tr>
@empty
                    <tr><td colspan="5" class="empty">Belum ada penggunaan AI.</td></tr>
@endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Aktivitas AI Terbaru</h3>
@forelse($recent as $r)
        <div class="list-row" style="align-items:flex-start;gap:12px">
            <div style="min-width:0;flex:1">
                <div class="split" style="gap:8px;align-items:center">
                    <strong>{{ \App\Support\SirkelUi::label($r->feature) }}</strong>
                    <span class="badge {{ $r->status === 'failed' ? 'danger' : 'success' }}">{{ \App\Support\SirkelUi::label($r->status) }}</span>
                </div>
                <div class="text-sm muted">{{ $r->model }} · {{ $r->created_at->format('d M H:i') }} · {{ number_format((int) $r->latency_ms) }} ms</div>
                <div class="text-sm muted">{{ number_format((int) $r->input_tokens) }} token masuk · {{ number_format((int) $r->output_tokens) }} token jawaban · ${{ number_format($r->estimated_cost_usd,6) }}</div>
@if($r->status === 'failed' && filled($r->error_message))
                <details class="mt-8">
                    <summary class="text-sm" style="cursor:pointer">Lihat detail teknis</summary>
                    <div class="text-sm muted mt-8" style="overflow-wrap:anywhere">{{ $r->error_message }}</div>
                </details>
@endif
            </div>
        </div>
@empty
        <div class="empty">Belum ada aktivitas AI.</div>
@endforelse
    </div>
</div>
@endsection
