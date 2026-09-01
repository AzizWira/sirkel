@extends('layouts.app')
@section('title','Bulk AI · SIRKEL')
@section('topbar','Bulk AI · PRO')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">PRO · AI</span><h2>Bulk AI</h2><p>Gunakan beberapa foto untuk membantu mengenali banyak elektronik sekaligus, kelompokkan barang sejenis, lalu cek kondisinya dalam satu proses.</p></div>
    <a class="btn" href="{{ route('user.cart.index') }}">Keranjang</a>
</div>

@if($activeSessions->isNotEmpty())
<section class="card stack mb-16 bulk-resume-card">
    <div class="split">
        <div><span class="eyebrow">Sesi tersimpan</span><h3>Lanjutkan Bulk AI yang belum selesai</h3><p class="muted mb-0">Sesi ini sudah memakai kuota ketika foto berhasil diproses. Melanjutkannya tidak memotong kuota lagi.</p></div>
        <span class="badge warning">{{ $activeSessions->count() }} aktif</span>
    </div>
    @foreach($activeSessions as $activeSession)
        @php
            $resumeUrl = match($activeSession->status) {
                \App\Models\IntakeSession::STATUS_DRAFT => route('user.bulk.edit', $activeSession),
                \App\Models\IntakeSession::STATUS_QUESTIONNAIRE => route('user.bulk.questionnaire', $activeSession),
                default => route('user.intake.review', $activeSession),
            };
            $stageLabel = match($activeSession->status) {
                \App\Models\IntakeSession::STATUS_DRAFT => 'Periksa hasil pengelompokan',
                \App\Models\IntakeSession::STATUS_QUESTIONNAIRE => 'Lanjutkan pertanyaan kondisi',
                default => 'Tinjau rekomendasi dan penyerahan',
            };
        @endphp
        <div class="cart-session-row">
            <div>
                <strong>Bulk AI · {{ $activeSession->items->count() }} kelompok</strong>
                <div class="muted text-sm">{{ $stageLabel }} · diperbarui {{ $activeSession->updated_at->diffForHumans() }}</div>
            </div>
            <a class="btn btn-primary btn-sm" href="{{ $resumeUrl }}">Lanjutkan Sesi</a>
        </div>
    @endforeach
</section>
@endif

<div class="bulk-hero-grid">
    <div class="card stack">
        <div class="split"><div><h3>Mulai sesi Bulk baru</h3><p class="muted mb-0">Tambahkan 1–3 foto satu per satu. Setiap foto dapat ditinjau, diganti, atau dihapus sebelum diproses.</p></div><div class="quota-pill"><strong>{{ number_format($quota['remaining']) }}×</strong><span>sesi tersisa</span></div></div>
        <div class="hint-box"><strong>Sebelum mulai</strong><br>• Maksimal 5 <b>kelompok</b> barang, bukan 5 benda fisik.<br>• Barang sejenis dapat digabung, misalnya 3 kabel charger = 1 kelompok ×3.<br>• Hasil dari foto selalu dapat Anda edit, hapus, atau lengkapi manual sebelum lanjut.<br>• Satu sesi yang berhasil dikenali memakai 1 kuota. Melanjutkan sesi yang sama tidak memakai kuota lagi.</div>
        @if($quota['exhausted'])
            <div class="alert warning">Kuota Bulk AI habis. Sesi lama di atas tetap dapat dilanjutkan tanpa kuota baru. Pendaftaran biasa dan Keranjang juga tetap tersedia.</div>
            <a class="btn btn-primary" href="{{ route('user.ai-quota.index') }}">Tambah Kuota Bulk AI</a>
        @else
            <form class="stack" method="post" enctype="multipart/form-data" action="{{ route('user.bulk.store') }}" data-bulk-ai-start-form>
                @csrf
                <div class="field">
                    <label>Foto barang * — maksimal 3</label>
                    <div class="bulk-photo-picker" data-bulk-photo-picker data-max-files="3" data-max-size-mb="5">
                        <div class="bulk-photo-actions">
                            <button class="btn" type="button" data-bulk-photo-gallery><x-icon name="image" size="16"/> Pilih Foto</button>
                            <button class="btn" type="button" data-bulk-photo-camera><x-icon name="camera" size="16"/> Kamera</button>
                        </div>
                        <small>Tambahkan foto satu per satu sampai maksimal 3. Setiap foto langsung muncul sebagai preview dan dapat diganti atau dihapus. JPG, PNG, atau WebP; maksimal 5 MB per foto.</small>
                        <div class="form-message" data-bulk-photo-status aria-live="polite">Tambahkan minimal 1 foto.</div>
                        <div class="bulk-photo-preview-grid" data-bulk-photo-preview></div>
                        <div class="split bulk-photo-picker-footer"><small>Foto asli tetap disimpan; salinan yang lebih ringan hanya digunakan saat analisis AI.</small><strong data-bulk-photo-count>0/3 foto</strong></div>
                        <div class="bulk-photo-input-host" data-bulk-photo-input-host aria-hidden="true"></div>
                    </div>
                </div>

                <div class="bulk-ai-progress" data-bulk-ai-progress hidden aria-live="polite">
                    <div class="bulk-ai-progress-icon"><x-icon name="sparkles" size="22"/></div>
                    <div><strong data-bulk-ai-progress-title>Menyiapkan foto...</strong><p class="muted mb-0" data-bulk-ai-progress-copy>SIRKEL memeriksa foto sebelum mengirim salinan yang telah dioptimalkan ke AI.</p></div>
                </div>
                <button class="btn btn-primary" type="submit" data-bulk-ai-start-button><x-icon name="sparkles" size="16"/> Proses Bulk dengan AI</button>
            </form>
        @endif
    </div>
    <div class="card stack">
        <h3>Kenapa berbeda dari Pengenalan Barang biasa?</h3>
        <p class="muted">Pengenalan Barang biasa membantu satu barang. Bulk AI membantu beberapa kelompok sekaligus dan menyiapkan pertanyaan bersama agar Anda tidak perlu menjawab hal yang sama berkali-kali.</p>
        <div class="bulk-example">
            <div><span>2×</span><strong>Kabel Charger</strong><small>tetap 1 kelompok</small></div>
            <div><span>1×</span><strong>Powerbank</strong><small>kelompok kedua</small></div>
            <div><span>3×</span><strong>Baterai</strong><small>kelompok ketiga</small></div>
        </div>
        <div class="hint-box">Pertanyaan dibuat secukupnya sesuai barang yang terdeteksi, dengan batas <strong>maksimal 15 pertanyaan untuk seluruh sesi</strong>.</div>
    </div>
</div>
@endsection


@section('modals')
<div id="bulk-camera-modal" class="modal-backdrop asset-media-modal" aria-hidden="true" data-bulk-camera-modal>
    <div class="modal asset-camera-card" role="dialog" aria-modal="true" aria-labelledby="bulk-camera-title">
        <div class="asset-modal-head">
            <div><h3 id="bulk-camera-title">Ambil Foto Bulk</h3><p class="muted mb-0">Kamera menampilkan seluruh frame tanpa crop. Area yang terlihat inilah yang akan masuk ke Bulk AI dan masih dapat dihapus sebelum diproses.</p></div>
            <button class="icon-button" type="button" aria-label="Tutup kamera" data-bulk-camera-close>×</button>
        </div>
        <div class="asset-camera-stage"><video autoplay playsinline muted data-bulk-camera-video></video></div>
        <p class="muted" data-bulk-camera-state>Menyiapkan kamera...</p>
        <div class="cluster asset-modal-actions">
            <button class="btn" type="button" data-bulk-camera-close>Batal</button>
            <button class="btn" type="button" data-bulk-camera-native>Buka Kamera Ponsel</button>
            <button class="btn btn-primary" type="button" data-bulk-camera-capture disabled>Ambil Foto</button>
        </div>
    </div>
</div>
@endsection
