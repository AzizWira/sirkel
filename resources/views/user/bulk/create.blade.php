@extends('layouts.app')
@section('title','Bulk AI · SIRKEL')
@section('topbar','Bulk AI · PRO')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">PRO · AI</span><h2>Bulk AI</h2><p>Gunakan beberapa foto untuk membantu mengenali banyak elektronik sekaligus, kelompokkan barang sejenis, lalu cek kondisinya dalam satu proses.</p></div>
    <a class="btn" href="{{ route('user.cart.index') }}">Keranjang</a>
</div>
<div class="bulk-hero-grid">
    <div class="card stack">
        <div class="split"><div><h3>Mulai sesi Bulk</h3><p class="muted mb-0">Unggah 1–3 foto yang mewakili barang dalam satu keadaan.</p></div><div class="quota-pill"><strong>{{ number_format($quota['remaining']) }}×</strong><span>sesi tersisa</span></div></div>
        <div class="hint-box"><strong>Sebelum mulai</strong><br>• Maksimal 5 <b>kelompok</b> barang, bukan 5 benda fisik.<br>• Barang sejenis dapat digabung, misalnya 3 kabel charger = 1 kelompok ×3.<br>• Hasil dari foto selalu dapat Anda edit, hapus, atau lengkapi manual sebelum lanjut.<br>• Satu sesi yang berhasil dikenali memakai 1 kuota. Melanjutkan sesi yang sama tidak memakai kuota lagi.</div>
        @if($quota['exhausted'])
            <div class="alert warning">Kuota Bulk AI habis. Pendaftaran biasa dan Keranjang tetap dapat digunakan tanpa Bulk AI.</div>
            <a class="btn btn-primary" href="{{ route('user.ai-quota.index') }}">Tambah Kuota Bulk AI</a>
        @else
            <form class="stack" method="post" enctype="multipart/form-data" action="{{ route('user.bulk.store') }}">
                @csrf
                <div class="field"><label>Foto barang * — maksimal 3</label><div class="camera-file-picker" data-camera-file-picker data-max-files="3"><input class="input" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-camera-main-input><input type="file" accept="image/*" capture="environment" hidden data-camera-capture-input><div class="cluster mt-8"><button class="btn btn-sm" type="button" data-camera-gallery>Pilih Foto</button><button class="btn btn-sm" type="button" data-camera-capture><x-icon name="camera" size="15"/> Kamera</button></div></div><small>JPG/PNG/WebP, maksimal 5 MB per foto. Foto dikirim ke AI hanya saat Anda menekan tombol mulai.</small></div>
                <button class="btn btn-primary" type="submit"><x-icon name="sparkles" size="16"/> Proses Bulk dengan AI</button>
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
