@extends('layouts.public')

@section('title','Cara Mengelola E-Waste & Elektronik Bekas | SIRKEL')
@section('meta_description','Pelajari cara menangani e-waste: kenali kondisi barang, gunakan bantuan AI SIRKEL bila diperlukan, lindungi data pribadi, dan pilih jalur penanganan yang sesuai.')

@section('content')
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Edukasi e-waste</div>
                <h1 style="font-size:48px">Elektronik rusak tidak selalu harus dibuang.</h1>
                <p class="lead">Sebelum menjadi sampah elektronik, cek dulu apakah barang masih bisa digunakan, diperbaiki, atau disalurkan. Jika tidak, pilih penanganan yang sesuai dengan kondisi dan risikonya.</p>
            </div>
        </div>

        <div class="grid-3">
            <article class="card"><h3>Utamakan guna ulang</h3><p class="muted mb-0">Barang yang masih berfungsi bisa terus dipakai, dialihkan, atau didonasikan agar masa gunanya lebih panjang.</p></article>
            <article class="card"><h3>Cek kemungkinan perbaikan</h3><p class="muted mb-0">Kerusakan ringan atau sebagian fungsi yang bermasalah belum tentu membuat perangkat tidak layak digunakan.</p></article>
            <article class="card"><h3>Lindungi data pribadi</h3><p class="muted mb-0">Untuk perangkat yang menyimpan data—misalnya ponsel, komputer, hard disk/SSD eksternal, kamera, atau konsol—cadangkan data yang dibutuhkan, keluar dari akun, lepaskan media penyimpanan yang ingin disimpan, lalu hapus data atau reset perangkat bila memungkinkan.</p></article>
            <article class="card"><h3>Perhatikan kondisi baterai</h3><p class="muted mb-0">Baterai yang menggembung, bocor, panas berlebih, atau rusak perlu ditangani dengan hati-hati. Jangan membongkarnya sendiri.</p></article>
            <article class="card"><h3>Pilih pemulihan yang sesuai</h3><p class="muted mb-0">Jika barang tidak lagi layak pakai atau diperbaiki, komponen dan materialnya masih dapat memiliki nilai untuk dipulihkan.</p></article>
            <article class="card"><h3>Simpan jejak penanganan</h3><p class="muted mb-0">Catatan perjalanan membantu memastikan status barang dan hasil penanganannya dapat ditelusuri kembali.</p></article>
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Bantuan saat mengenali kondisi</div>
                <h2>Tidak yakin nama barang atau cara menjelaskan kondisinya?</h2>
                <p class="lead">Di aplikasi SIRKEL, bantuan AI dapat dipakai secara opsional untuk mengenali elektronik dari foto dan merapikan catatan kondisi. Anda tetap memeriksa sarannya sendiri, dan hasil akhir penanganan tetap berdasarkan proses serta pemeriksaan mitra.</p>
            </div>
        </div>
        <div class="grid-3">
            <article class="card"><h3>Foto hanya jika Anda memilih</h3><p class="muted mb-0">Mengunggah foto tidak otomatis menjalankan AI. Bantuan foto baru digunakan setelah Anda memilih tombol bantuan AI.</p></article>
            <article class="card"><h3>Saran dapat diubah</h3><p class="muted mb-0">Jenis barang dan catatan yang disarankan dapat diperiksa, dipilih sebagian, diperbaiki, atau diabaikan sebelum disimpan.</p></article>
            <article class="card"><h3>Bukan penentu hasil akhir</h3><p class="muted mb-0">AI membantu pengisian awal. Keputusan penanganan mengikuti data kondisi dan pemeriksaan mitra yang benar-benar menangani barang.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container seo-copy">
        <div class="eyebrow">Apa itu e-waste?</div>
        <h2>Sampah elektronik lebih dari sekadar barang yang sudah tidak dipakai.</h2>
        <p class="lead">E-waste mencakup perangkat listrik dan elektronik yang tidak lagi digunakan atau dibuang. Ponsel, komputer, televisi, printer, perangkat audio, konsol game, baterai, kulkas, mesin cuci, AC, peralatan dapur elektronik, hingga perkakas listrik dapat masuk kategori ini. Penanganannya sebaiknya mempertimbangkan kondisi barang, potensi guna ulang, keamanan data, baterai atau komponen berisiko, serta kemungkinan pemulihan komponen dan material.</p>
        <div class="hero-actions"><a class="btn btn-primary" href="{{ route('register') }}">Daftarkan Elektronik</a><a class="btn" href="{{ route('public.partners') }}">Lihat Mitra</a></div>
    </div>
</section>
@endsection
