@extends('layouts.app')

@section('title', 'Pengaturan · SIRKEL')
@section('topbar', 'Pengaturan')

@section('content')
    <div class="page-head">
        <div>
            <h2>Pengaturan Operasional</h2>
            <p>Kelola bantuan AI, data wilayah, dan pengaturan operasional SIRKEL.</p>
        </div>
    </div>

    <div class="two-col">
        <form class="card stack" method="post" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <h3>AI & Perlindungan Biaya</h3>

            <div class="hint-box">
                <strong>Status AI</strong>
                <div class="text-sm mt-8">
                    @if($aiStatus['enabled'] && $aiStatus['api_key_configured'] && !$aiStatus['budget_reached'])
                        Siap digunakan.
                    @elseif(!$aiStatus['enabled'])
                        Sedang dinonaktifkan.
                    @elseif(!$aiStatus['api_key_configured'])
                        Koneksi AI belum siap.
                    @else
                        Batas anggaran bulanan sudah tercapai.
                    @endif
                </div>
                <div class="text-sm muted mt-8">
                    Pemakaian bulan ini: ${{ number_format($aiStatus['used'], 4) }} /
                    ${{ number_format($aiStatus['budget'], 2) }}
                </div>

                <details class="mt-8">
                    <summary>Detail teknis</summary>
                    <div class="text-sm muted mt-8">
                        API key: {{ $aiStatus['api_key_configured'] ? 'tersedia' : 'belum tersedia' }}
                    </div>
                    @if($aiStatus['last_failure'])
                        <div class="text-sm mt-8">
                            <strong>Gangguan terakhir:</strong>
                            {{ \Illuminate\Support\Str::limit($aiStatus['last_failure'], 240) }}
                            @if($aiStatus['last_failure_at'])
                                <span class="muted">· {{ $aiStatus['last_failure_at']->diffForHumans() }}</span>
                            @endif
                        </div>
                    @endif
                </details>
            </div>

            <label class="choice">
                <input type="checkbox" name="ai_enabled" value="1" {{ ($settings['ai.enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                <span><strong>AI aktif</strong><br><small class="muted">Jika dinonaktifkan, fitur bantuan AI tidak
                        tersedia.</small></span>
            </label>

            <div class="field">
                <label>Anggaran bulanan (USD)</label>
                <input class="input" type="number" min="0" step="0.01" name="ai_monthly_budget_usd"
                    value="{{ $settings['ai.monthly_budget_usd'] ?? config('sirkel.ai.monthly_budget_usd') }}" required>
            </div>
            <div class="field">
                <label>Model utama</label>
                <input class="input mono" name="ai_default_model"
                    value="{{ $settings['ai.default_model'] ?? config('sirkel.ai.default_model') }}" required>
            </div>
            <div class="field">
                <label>Model cadangan untuk kasus sulit</label>
                <input class="input mono" name="ai_escalation_model"
                    value="{{ $settings['ai.escalation_model'] ?? config('sirkel.ai.escalation_model') }}" required>
            </div>
            <div class="field">
                <label>Batas keyakinan untuk memakai model cadangan</label>
                <input class="input" type="number" min="0" max="1" step="0.05" name="ai_escalation_confidence"
                    value="{{ $settings['ai.escalation_confidence'] ?? config('sirkel.ai.escalation_confidence') }}" required>
                <small>Contoh 0.65: model cadangan digunakan ketika tingkat keyakinan pengenalan barang di bawah
                    65%.</small>
            </div>
            <div class="field">
                <label>Detail analisis gambar</label>
                <select class="select" name="ai_image_detail">
                    <option value="low" {{ ($settings['ai.image_detail'] ?? 'low') === 'low' ? 'selected' : '' }}>Rendah — hemat
                    </option>
                    <option value="auto" {{ ($settings['ai.image_detail'] ?? '') === 'auto' ? 'selected' : '' }}>Otomatis</option>
                    <option value="high" {{ ($settings['ai.image_detail'] ?? '') === 'high' ? 'selected' : '' }}>Tinggi</option>
                </select>
                <small>Pilihan ini hanya mengatur detail gambar saat bantuan analisis foto digunakan.</small>
            </div>

            <div class="hint-box mt-16">
                <strong>Kuota AI & Top Up</strong>
                <div class="text-sm muted mt-8">Kuota gratis berlaku per akun warga. Setelah habis, warga dapat membuat
                    permintaan top up yang dikirim ke WhatsApp admin. Pembayaran tidak diproses di dalam SIRKEL.</div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Kuota gratis Pengenalan Barang</label>
                    <input class="input" type="number" min="0" max="1000" name="ai_quota_asset_intake_free"
                        value="{{ $settings['ai.quota.asset_intake_free'] ?? config('sirkel.ai.quota.asset_intake_free', 5) }}"
                        required>
                    <small>Jumlah penggunaan awal fitur Proses dengan AI dari foto per akun.</small>
                </div>
                <div class="field">
                    <label>Kuota gratis Penyusunan Catatan</label>
                    <input class="input" type="number" min="0" max="5000" name="ai_quota_condition_description_free"
                        value="{{ $settings['ai.quota.condition_description_free'] ?? config('sirkel.ai.quota.condition_description_free', 20) }}"
                        required>
                    <small>Jumlah penggunaan awal fitur Buat deskripsi dengan AI per akun.</small>
                </div>
                <div class="field">
                    <label>Kuota gratis Bulk AI</label>
                    <input class="input" type="number" min="0" max="1000" name="ai_quota_bulk_ai_free"
                        value="{{ $settings['ai.quota.bulk_ai_free'] ?? config('sirkel.ai.quota.bulk_ai_free', 3) }}" required>
                    <small>Jumlah sesi Bulk AI awal per akun. Satu sesi berhasil = satu pemakaian, resume tidak memotong
                        lagi.</small>
                </div>
                <div class="field">
                    <label>Harga 1× Pengenalan Barang (Rp)</label>
                    <input class="input" type="number" min="0" max="10000000" step="1"
                        name="ai_quota_asset_intake_price_idr"
                        value="{{ $settings['ai.quota.asset_intake_price_idr'] ?? config('sirkel.ai.quota.asset_intake_price_idr', 2000) }}"
                        required>
                </div>
                <div class="field">
                    <label>Harga 1× Penyusunan Catatan (Rp)</label>
                    <input class="input" type="number" min="0" max="10000000" step="1"
                        name="ai_quota_condition_description_price_idr"
                        value="{{ $settings['ai.quota.condition_description_price_idr'] ?? config('sirkel.ai.quota.condition_description_price_idr', 500) }}"
                        required>
                </div>
                <div class="field">
                    <label>Harga 1 sesi Bulk AI (Rp)</label>
                    <input class="input" type="number" min="0" max="10000000" step="1" name="ai_quota_bulk_ai_price_idr"
                        value="{{ $settings['ai.quota.bulk_ai_price_idr'] ?? config('sirkel.ai.quota.bulk_ai_price_idr', 5000) }}"
                        required>
                </div>
                <div class="field full">
                    <label>WhatsApp Admin untuk Top Up</label>
                    <input class="input" name="ai_topup_admin_whatsapp"
                        value="{{ $settings['ai.topup_admin_whatsapp'] ?? '628111111111' }}"
                        placeholder="Contoh: 628123456789" required>
                    <small>Gunakan format kode negara. Pesan top up warga akan diarahkan ke nomor ini.</small>
                </div>
            </div>
            <button class="btn btn-primary">Simpan Pengaturan AI</button>
        </form>

        <div class="stack">
            <div class="card">
                <h3>Data Wilayah Surabaya</h3>
                <p class="muted">SIRKEL sudah membawa daftar kecamatan dan kelurahan Surabaya untuk kebutuhan form warga dan
                    mitra.</p>
                <form method="post" action="{{ route('admin.settings.sync-regions') }}">
                    @csrf
                    <button class="btn">Perbarui dari BinderByte</button>
                </form>
                <div class="hint-box mt-16">Pembaruan ini bersifat opsional. Jika layanan wilayah sedang tidak tersedia,
                    daftar bawaan SIRKEL tetap dapat digunakan.</div>
            </div>
            <div class="card">
                <h3>Pengelolaan Privasi</h3>
                <p class="muted">KTP mitra disimpan secara terlindungi untuk proses verifikasi dan dijadwalkan untuk dihapus
                    {{ config('sirkel.ktp_retention_days') }} hari setelah keputusan verifikasi.</p>
                <div class="hint-box mt-16">Jika penghapusan tidak berhasil, admin akan menerima pemberitahuan untuk
                    ditindaklanjuti.</div>
            </div>
        </div>
    </div>
@endsection