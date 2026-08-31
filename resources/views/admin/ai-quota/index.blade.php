@extends('layouts.app')

@section('title','Top Up Kuota AI · SIRKEL')
@section('topbar','Top Up Kuota AI')

@section('content')
<div class="page-head">
    <div>
        <h2>Top Up Kuota AI</h2>
        <p>Kelola permintaan kuota AI yang dikirim warga melalui WhatsApp. Pembayaran tidak diproses di dalam SIRKEL.</p>
    </div>
    <a class="btn" href="{{ route('admin.settings.edit') }}">Atur Harga & Kuota</a>
</div>

<div class="kpi-grid">
    <div class="kpi"><span class="metric-label">Menunggu</span><strong>{{ number_format($pendingCount) }}</strong></div>
    <div class="kpi"><span class="metric-label">Disetujui</span><strong>{{ number_format($approvedCount) }}</strong></div>
    <div class="kpi"><span class="metric-label">Nominal request disetujui</span><strong>Rp{{ number_format($approvedRevenue,0,',','.') }}</strong><small class="muted">Bukan pencatatan pembayaran di SIRKEL.</small></div>
</div>

<div class="card mt-16">
    <div class="cluster mb-16">
        @foreach(['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','all'=>'Semua'] as $key=>$label)
            <a class="btn btn-sm {{ $status===$key?'btn-primary':'' }}" href="{{ route('admin.ai-quota.index',['status'=>$key]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="table-wrap">
        <table class="mobile-table mobile-table-6">
            <thead><tr><th>Permintaan</th><th>Warga</th><th>Kuota</th><th>Nominal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($requests as $item)
                <tr>
                    <td><span class="code">TP-{{ strtoupper(substr($item->public_id,-8)) }}</span><br><small class="muted">{{ optional($item->requested_at)->format('d M Y H:i') }}</small></td>
                    <td><strong>{{ $item->user->name }}</strong><br><small class="muted">{{ $item->user->email }}</small></td>
                    <td>Foto +{{ $item->asset_intake_quantity }}×<br>Deskripsi +{{ $item->condition_description_quantity }}×<br>Bulk +{{ $item->bulk_ai_quantity }} sesi</td>
                    <td>Rp{{ number_format($item->total_amount_idr,0,',','.') }}</td>
                    <td><span class="badge {{ $item->status==='approved'?'success':($item->status==='rejected'?'danger':'warning') }}">{{ \App\Support\SirkelUi::label($item->status) }}</span></td>
                    <td><a class="btn btn-sm" href="{{ route('admin.ai-quota.show',$item) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Tidak ada permintaan pada status ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-16">{{ $requests->links() }}</div>
</div>
@endsection
