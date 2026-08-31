@extends('layouts.app')

@section('title', 'Kuota AI · SIRKEL')
@section('topbar', 'Kuota AI')

@section('content')
    <div class="page-head">
        <div>
            <h2>Kuota AI</h2>
            <p>AI bersifat opsional. Fitur inti SIRKEL tetap dapat digunakan tanpa menambah kuota.</p>
        </div>
    </div>

    <div class="kpi-grid">
        @foreach($quotas as $quota)
            <div class="kpi">
                <span class="metric-label">{{ $quota['label'] }}</span>
                <strong>{{ number_format($quota['remaining']) }}×</strong>
                <small class="muted">Tersisa dari {{ number_format($quota['total_quota']) }} kuota ·
                    {{ number_format($quota['used']) }} sudah digunakan</small>
            </div>
        @endforeach
    </div>

    <div class="two-col mt-16">
        <div class="card stack">
            <div>
                <h3>Tambah Kuota AI</h3>
                <p class="muted mb-0">Tidak ada pembayaran di dalam SIRKEL. Permintaan dibuat di sini lalu dikirim ke
                    WhatsApp admin untuk diproses secara manual.</p>
            </div>

            @if($pending)
                <div class="alert warning">
                    <strong>Ada permintaan yang masih menunggu.</strong><br>
                    Permintaan {{ 'TP-' . strtoupper(substr($pending->public_id, -8)) }} belum diproses admin. Jangan membuat
                    permintaan baru agar kuota tidak terduplikasi.
                </div>
                <div class="card" style="box-shadow:none">
                    <div class="split"><span>Pengenalan
                            Barang</span><strong>+{{ number_format($pending->asset_intake_quantity) }}×</strong></div>
                    <div class="split mt-8"><span>Penyusunan Catatan
                            Kondisi</span><strong>+{{ number_format($pending->condition_description_quantity) }}×</strong></div>
                    <div class="split mt-8"><span>Bulk AI</span><strong>+{{ number_format($pending->bulk_ai_quantity) }}
                            sesi</strong></div>
                    <div class="split mt-8"><span>Total
                            permintaan</span><strong>Rp{{ number_format($pending->total_amount_idr, 0, ',', '.') }}</strong></div>
                </div>
                <div class="hint-box"><strong>⚠️ Penting:</strong> pesan WhatsApp dibuat otomatis. <strong>Dilarang mengubah,
                        menghapus, menambahkan, atau menyusun ulang format pesan.</strong> Kirim apa adanya agar admin dapat
                    memproses kode permintaan dengan benar.</div>
                @if($pendingWhatsappUrl)
                    <a class="btn btn-primary" href="{{ $pendingWhatsappUrl }}" target="_blank" rel="noopener">Kirim Ulang ke
                        WhatsApp Admin</a>
                @else
                    <div class="alert warning">Nomor WhatsApp admin belum dikonfigurasi. Hubungi admin dan sebutkan kode permintaan
                        di atas.</div>
                @endif
            @else
                <form class="stack" method="post" action="{{ route('user.ai-quota.store') }}" data-ai-topup-form
                    data-intake-price="{{ $quotas['asset_intake']['unit_price_idr'] }}"
                    data-description-price="{{ $quotas['citizen_condition_description']['unit_price_idr'] }}"
                    data-bulk-price="{{ $quotas['bulk_ai']['unit_price_idr'] }}">
                    @csrf
                    <div class="field">
                        <label>Tambahan Pengenalan Barang</label>
                        <input class="input" type="number" min="0" max="500" step="1" name="asset_intake_quantity"
                            value="{{ old('asset_intake_quantity', 0) }}" data-ai-topup-intake>
                        <small>Rp{{ number_format($quotas['asset_intake']['unit_price_idr'], 0, ',', '.') }} per 1 kali Proses
                            dengan AI dari foto.</small>
                    </div>
                    <div class="field">
                        <label>Tambahan Penyusunan Catatan Kondisi</label>
                        <input class="input" type="number" min="0" max="1000" step="1" name="condition_description_quantity"
                            value="{{ old('condition_description_quantity', 0) }}" data-ai-topup-description>
                        <small>Rp{{ number_format($quotas['citizen_condition_description']['unit_price_idr'], 0, ',', '.') }} per 1
                            kali Buat deskripsi dengan AI.</small>
                    </div>
                    <div class="field">
                        <label>Tambahan Bulk AI</label>
                        <input class="input" type="number" min="0" max="500" step="1" name="bulk_ai_quantity"
                            value="{{ old('bulk_ai_quantity', 0) }}" data-ai-topup-bulk>
                        <small>Rp{{ number_format($quotas['bulk_ai']['unit_price_idr'], 0, ',', '.') }} per 1 sesi Bulk AI. Satu
                            sesi dapat memproses maksimal 5 kelompok.</small>
                    </div>
                    <div class="hint-box">
                        <div class="split"><strong>Perkiraan total</strong><strong data-ai-topup-total>Rp0</strong></div>
                        <div class="text-sm muted mt-8">Harga dikunci saat permintaan dibuat. Admin hanya mengaktifkan kuota
                            setelah proses manual di luar SIRKEL selesai.</div>
                    </div>
                    <div class="alert warning"><strong>⚠️ Sebelum lanjut:</strong> SIRKEL akan membuka WhatsApp dengan format
                        pesan otomatis. <strong>Dilarang mengubah format teks tersebut.</strong></div>
                    <button class="btn btn-primary" type="submit">Buat Permintaan & Buka WhatsApp</button>
                </form>
            @endif
        </div>

        <div class="card stack">
            <div>
                <h3>Riwayat Top Up</h3>
                <p class="muted mb-0">Top up hanya menambah kuota fitur AI warga. Penyerahan barang, rekomendasi, dan
                    layanan mitra tidak terkunci oleh kuota AI.</p>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Kuota</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                <td><span class="code">TP-{{ strtoupper(substr($item->public_id, -8)) }}</span><br><small
                                        class="muted">{{ optional($item->requested_at)->format('d M Y H:i') }}</small></td>
                                <td>Foto +{{ $item->asset_intake_quantity }}×<br>Deskripsi
                                    +{{ $item->condition_description_quantity }}×<br>Bulk +{{ $item->bulk_ai_quantity }} sesi
                                </td>
                                <td>Rp{{ number_format($item->total_amount_idr, 0, ',', '.') }}</td>
                                <td><span
                                        class="badge {{ $item->status === 'approved' ? 'success' : ($item->status === 'rejected' ? 'danger' : 'warning') }}">{{ \App\Support\SirkelUi::label($item->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty">Belum ada riwayat top up.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-ai-topup-form]');
            if (!form) return;
            const intake = form.querySelector('[data-ai-topup-intake]');
            const description = form.querySelector('[data-ai-topup-description]');
            const bulk = form.querySelector('[data-ai-topup-bulk]');
            const total = form.querySelector('[data-ai-topup-total]');
            const format = value => 'Rp' + new Intl.NumberFormat('id-ID').format(value);
            const update = () => {
                const a = Math.max(0, Number(intake?.value || 0));
                const b = Math.max(0, Number(description?.value || 0));
                const c = Math.max(0, Number(bulk?.value || 0));
                total.textContent = format((a * Number(form.dataset.intakePrice || 0)) + (b * Number(form.dataset.descriptionPrice || 0)) + (c * Number(form.dataset.bulkPrice || 0)));
            };
            intake?.addEventListener('input', update);
            description?.addEventListener('input', update);
            bulk?.addEventListener('input', update);
            update();
        });
    </script>
@endpush