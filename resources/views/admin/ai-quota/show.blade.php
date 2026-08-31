@extends('layouts.app')

@section('title', 'Detail Top Up AI · SIRKEL')
@section('topbar', 'Detail Top Up AI')

@section('content')
    <div class="page-head">
        <div>
            <a class="text-sm" href="{{ route('admin.ai-quota.index') }}">← Kembali ke Top Up AI</a>
            <h2 class="mt-8">{{ 'TP-' . strtoupper(substr($topup->public_id, -8)) }}</h2>
            <p>Permintaan {{ optional($topup->requested_at)->format('d M Y H:i') }}</p>
        </div>
        <span
            class="badge {{ $topup->status === 'approved' ? 'success' : ($topup->status === 'rejected' ? 'danger' : 'warning') }}">{{ \App\Support\SirkelUi::label($topup->status) }}</span>
    </div>

    <div class="two-col">
        <div class="stack">
            <div class="card stack">
                <h3>Data Warga</h3>
                <div class="split"><span>Nama</span><strong>{{ $topup->user->name }}</strong></div>
                <div class="split"><span>Email</span><strong>{{ $topup->user->email }}</strong></div>
                <div class="split"><span>Nomor HP</span><strong>{{ $topup->user->whatsapp ?: '-' }}</strong></div>
            </div>

            <div class="card stack">
                <h3>Permintaan Kuota</h3>
                <div class="split"><span>Pengenalan
                        Barang</span><strong>+{{ number_format($topup->asset_intake_quantity) }}×</strong></div>
                <div class="split"><span>Harga per
                        penggunaan</span><span>Rp{{ number_format($topup->asset_intake_unit_price_idr, 0, ',', '.') }}</span>
                </div>
                <div class="split mt-8"><span>Penyusunan Catatan
                        Kondisi</span><strong>+{{ number_format($topup->condition_description_quantity) }}×</strong></div>
                <div class="split"><span>Harga per
                        penggunaan</span><span>Rp{{ number_format($topup->condition_description_unit_price_idr, 0, ',', '.') }}</span>
                </div>
                <div class="split mt-8"><span>Bulk AI</span><strong>+{{ number_format($topup->bulk_ai_quantity) }}
                        sesi</strong></div>
                <div class="split"><span>Harga per
                        sesi</span><span>Rp{{ number_format($topup->bulk_ai_unit_price_idr, 0, ',', '.') }}</span></div>
                <div class="split mt-16"><strong>Total
                        request</strong><strong>Rp{{ number_format($topup->total_amount_idr, 0, ',', '.') }}</strong></div>
                <div class="hint-box">SIRKEL tidak memproses atau memverifikasi pembayaran. Approval di halaman ini berarti
                    admin telah menyelesaikan pemeriksaan manual di luar sistem dan siap mengaktifkan kuota.</div>
            </div>
        </div>

        <div class="stack">
            <div class="card stack">
                <h3>Kuota Akun Saat Ini</h3>
                @foreach($quotas as $quota)
                    <div class="split"><span>{{ $quota['label'] }}</span><strong>{{ number_format($quota['remaining']) }}×
                            tersisa</strong></div>
                    <small class="muted">{{ number_format($quota['used']) }} digunakan ·
                        {{ number_format($quota['topup_granted']) }} dari top up disetujui</small>
                @endforeach
            </div>

            @if($topup->isPending())
                <div class="card stack">
                    <h3>Keputusan Admin</h3>
                    <form method="post" action="{{ route('admin.ai-quota.approve', $topup) }}"
                        data-confirm="Setujui top up ini? Kuota tambahan akan langsung aktif pada akun warga.">
                        @csrf
                        <button class="btn btn-primary" style="width:100%">Setujui & Aktifkan Kuota</button>
                    </form>
                    <form class="stack" method="post" action="{{ route('admin.ai-quota.reject', $topup) }}"
                        data-confirm="Tolak permintaan top up ini?">
                        @csrf
                        <div class="field"><label>Alasan penolakan</label><textarea class="textarea" name="reason" required
                                minlength="3" maxlength="500"
                                placeholder="Contoh: konfirmasi pembayaran belum diterima"></textarea></div>
                        <button class="btn" style="width:100%">Tolak Permintaan</button>
                    </form>
                </div>
            @else
                <div class="card stack">
                    <h3>Sudah Diproses</h3>
                    <div class="split"><span>Diproses oleh</span><strong>{{ $topup->reviewer?->name ?: '-' }}</strong></div>
                    <div class="split">
                        <span>Waktu</span><strong>{{ optional($topup->reviewed_at)->format('d M Y H:i') ?: '-' }}</strong></div>
                    @if($topup->rejection_reason)
                        <div class="hint-box"><strong>Alasan:</strong> {{ $topup->rejection_reason }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection