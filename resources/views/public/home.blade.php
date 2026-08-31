@extends('layouts.public')

@section('title','SIRKEL | Pengelolaan E-Waste & Elektronik Sirkular Surabaya')
@section('meta_description','Kelola elektronik tak terpakai di Surabaya bersama SIRKEL. Gunakan bantuan AI secara opsional untuk mengenali barang, cek kondisi, temukan mitra, dan lacak penanganannya.')

@push('head')
@php
    $schemaAt = '@';
    $structuredData = [
        $schemaAt.'context' => 'https://schema.org',
        $schemaAt.'graph' => [
            [
                $schemaAt.'type' => 'Organization',
                $schemaAt.'id' => url('/').'#organization',
                'name' => 'SIRKEL',
                'url' => url('/'),
                'logo' => asset('brand/sirkel-icon.png'),
                'description' => 'Platform elektronik sirkular yang membantu warga Surabaya mengenali barang dengan bantuan AI opsional, memilih jalur penanganan, menemukan mitra, dan melacak hasilnya.',
                'areaServed' => [$schemaAt.'type' => 'City', 'name' => 'Surabaya'],
            ],
            [
                $schemaAt.'type' => 'WebSite',
                $schemaAt.'id' => url('/').'#website',
                'url' => url('/'),
                'name' => 'SIRKEL',
                'inLanguage' => 'id-ID',
                'publisher' => [$schemaAt.'id' => url('/').'#organization'],
            ],
            [
                $schemaAt.'type' => 'FAQPage',
                'mainEntity' => [
                    [$schemaAt.'type' => 'Question', 'name' => 'Apa itu e-waste?', 'acceptedAnswer' => [$schemaAt.'type' => 'Answer', 'text' => 'E-waste atau sampah elektronik adalah perangkat elektronik yang sudah tidak digunakan atau dibuang. Sebagian barang masih dapat digunakan kembali, diperbaiki, didonasikan, atau dipulihkan materialnya.']],
                    [$schemaAt.'type' => 'Question', 'name' => 'Barang apa yang bisa didaftarkan di SIRKEL?', 'acceptedAnswer' => [$schemaAt.'type' => 'Answer', 'text' => 'SIRKEL mencakup ponsel dan komputer, aksesori daya, elektronik rumah tangga kecil maupun besar, perangkat kantor, audio-video, gaming, perangkat perawatan pribadi, hingga perkakas elektrik. Jika nama barang belum ada, warga tetap dapat memakai kategori “Lainnya” yang paling mendekati.']],
                    [$schemaAt.'type' => 'Question', 'name' => 'Apakah semua barang akan didaur ulang?', 'acceptedAnswer' => [$schemaAt.'type' => 'Answer', 'text' => 'Tidak. SIRKEL mengutamakan jalur yang mempertahankan nilai barang, seperti guna ulang dan perbaikan, sebelum pemulihan material bila kondisinya memungkinkan.']],
                    [$schemaAt.'type' => 'Question', 'name' => 'Bagaimana SIRKEL memilih mitra?', 'acceptedAnswer' => [$schemaAt.'type' => 'Answer', 'text' => 'Mitra disaring berdasarkan status verifikasi, layanan yang disetujui, kategori barang, metode penyerahan, lokasi, dan radius penjemputan. Warga tetap memilih mitra yang diinginkan.']],
                    [$schemaAt.'type' => 'Question', 'name' => 'Apakah wajib menggunakan AI?', 'acceptedAnswer' => [$schemaAt.'type' => 'Answer', 'text' => 'Tidak. Bantuan AI bersifat opsional. Warga tetap dapat mengisi data barang, menyelesaikan cek kondisi, memilih mitra, dan mengikuti penanganan tanpa menggunakan AI.']],
                ],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="eyebrow">Elektronik sirkular di Surabaya</div>
            <h1>Elektronik tak terpakai masih punya jalan.</h1>
            <p class="lead">Daftarkan barang, gunakan bantuan AI dari foto bila diperlukan, cek kondisi dengan bahasa sederhana, lalu temukan mitra yang sesuai dan ikuti penanganannya sampai selesai.</p>
            <div class="hero-actions">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Buka Dashboard</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('user.assets.create') }}">Daftarkan Elektronik</a>
                    @endif
                @else
                    <a class="btn btn-primary" href="{{ route('register') }}">Daftarkan Elektronik</a>
                @endauth
                <a class="btn" href="{{ route('public.partners') }}">Cari Mitra</a>
            </div>
            <p class="text-sm muted" style="margin-top:18px">Mulai dari Gunung Anyar, untuk Surabaya.</p>
        </div>

        <div class="hero-panel">
            <div class="eyebrow">Dari barang ke jalur yang tepat</div>
            @foreach([
                ['01','Daftarkan','Foto atau isi manual sesuai yang Anda ketahui.'],
                ['02','Kenali & cek','AI dapat membantu mengenali barang; Anda tetap mengonfirmasi datanya.'],
                ['03','Pilih mitra','Bandingkan layanan, lokasi, dan cara serah terima.'],
                ['04','Lacak','Ikuti penanganan melalui Paspor SIRKEL sampai selesai.'],
            ] as $f)
                <div class="flow-line"><div class="flow-num">{{ $f[0] }}</div><div><strong>{{ $f[1] }}</strong><div class="text-sm muted">{{ $f[2] }}</div></div></div>
            @endforeach
        </div>
    </div>
</section>

<section class="section" id="bantuan-ai">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Bantuan AI</div>
                <h2>AI membantu saat dibutuhkan, bukan mengambil keputusan untuk Anda.</h2>
                <p class="lead">Gunakan AI untuk mempercepat bagian yang biasanya merepotkan. Semua saran dapat ditinjau, dipilih, diubah, atau diabaikan sebelum data disimpan.</p>
            </div>
        </div>
        <div class="grid-3">
            <article class="card">
                <h3>Kenali barang dari foto</h3>
                <p class="muted mb-0">Saat Anda memilihnya, AI dapat membantu mengenali jenis perangkat dan menyiapkan isian awal. Foto tidak dianalisis otomatis tanpa tindakan Anda.</p>
            </article>
            <article class="card">
                <h3>Rapikan catatan kondisi</h3>
                <p class="muted mb-0">Setelah pertanyaan kondisi dijawab, AI dapat membantu menyusun catatan singkat yang lebih mudah dibaca mitra tanpa mengubah jawaban Anda.</p>
            </article>
            <article class="card">
                <h3>Periksa beberapa barang sekaligus</h3>
                <p class="muted mb-0">Untuk banyak elektronik, Bulk AI membantu mengenali beberapa kelompok barang dan menyederhanakan pertanyaan yang perlu dijawab dalam satu proses.</p>
            </article>
        </div>
        <div class="hint-box mt-16"><strong>Tetap bisa tanpa AI.</strong> Pendaftaran, cek kondisi, rekomendasi awal, pemilihan mitra, penyerahan, dan pelacakan tetap dapat digunakan secara manual.</div>
    </div>

</section>

<section class="section alt">
    <div class="container">
        <div class="section-head">
            <div><div class="eyebrow">Dampak</div><h2>Hasil yang bisa dilacak.</h2></div>
        </div>
        <div class="grid-4">
            <div class="card"><div class="metric">{{ number_format($impact['verified_kg'],1,',','.') }} kg</div><div class="metric-label">Berat terverifikasi</div></div>
            <div class="card"><div class="metric">{{ $impact['verified_assets'] }}</div><div class="metric-label">Barang terverifikasi</div></div>
            <div class="card"><div class="metric">{{ number_format($impact['repair_kg'],1,',','.') }} kg</div><div class="metric-label">Berhasil diperbaiki</div></div>
            <div class="card"><div class="metric">{{ number_format($impact['recovery_kg'],1,',','.') }} kg</div><div class="metric-label">Pemulihan terkonfirmasi</div></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div><div class="eyebrow">Jalur penanganan</div><h2>Tidak semua elektronik rusak harus langsung didaur ulang.</h2></div>
        </div>
        <div class="grid-3">
            @foreach([
                ['Guna Ulang','Masih layak dipakai? Pertahankan barang dalam penggunaan selama mungkin.'],
                ['Perbaikan','Kerusakan tertentu masih bisa diperiksa dan diperbaiki.'],
                ['Donasi','Barang yang masih layak dapat diteruskan kepada pengguna lain.'],
                ['Pemulihan Komponen','Komponen yang masih bernilai dapat dipisahkan untuk dimanfaatkan kembali.'],
                ['Pemulihan Material','Material dari barang yang sudah tidak layak dapat diarahkan ke mitra yang sesuai.'],
                ['Penanganan Khusus','Baterai atau kondisi berisiko membutuhkan penanganan yang lebih aman.'],
            ] as $x)
                <article class="card"><h3>{{ $x[0] }}</h3><p class="muted mb-0">{{ $x[1] }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head">
            <div><div class="eyebrow">Mitra</div><h2>Temukan layanan yang sesuai dengan barang Anda.</h2></div>
            <a class="btn" href="{{ route('public.partners') }}">Lihat semua mitra</a>
        </div>
        <div class="grid-3">
            @forelse($partners as $p)
                <article class="card">
                    <div class="split"><h3>{{ $p->business_name }}</h3><span class="badge success">Terverifikasi</span></div>
                    <p class="muted mb-0">{{ $p->district }}, Surabaya · radius penjemputan {{ $p->pickup_radius_km }} km</p>
                </article>
            @empty
                <div class="empty">Mitra publik belum tersedia.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="section">
    <div class="container seo-copy">
        <div class="eyebrow">Pengelolaan e-waste</div>
        <h2>Mulai dari kondisi barang, bukan dari asumsi bahwa semuanya adalah sampah.</h2>
        <p class="lead">SIRKEL membantu warga Surabaya menangani elektronik yang sudah tidak digunakan dengan langkah yang lebih terarah. Bantuan AI dapat dipakai untuk mempercepat pengenalan barang dan penyusunan catatan, sementara keputusan penanganan tetap mengikuti kondisi barang dan pemeriksaan mitra. Barang yang masih berfungsi dapat dipakai kembali atau didonasikan, barang yang bermasalah dapat diperiksa untuk kemungkinan perbaikan, sedangkan barang yang tidak lagi layak dapat diarahkan ke pemulihan komponen, material, atau penanganan khusus.</p>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head"><div><div class="eyebrow">Pertanyaan umum</div><h2>Hal yang sering ditanyakan.</h2></div></div>
        <div class="stack faq-list">
            <details class="card faq-card"><summary>Apa itu e-waste?</summary><p class="muted mb-0">E-waste atau sampah elektronik adalah perangkat elektronik yang sudah tidak digunakan atau dibuang. Sebagian masih memiliki nilai guna dan tidak harus langsung masuk proses pemulihan material.</p></details>
            <details class="card faq-card"><summary>Barang apa yang bisa didaftarkan?</summary><p class="muted mb-0">Anda dapat mendaftarkan ponsel dan komputer, aksesori daya, elektronik rumah tangga kecil maupun besar, perangkat kantor, audio-video, gaming, perangkat perawatan pribadi, serta perkakas elektrik. Jika barang belum ada di daftar, pilih kategori “Lainnya” yang paling mendekati.</p></details>
            <details class="card faq-card"><summary>Apakah semua barang akan didaur ulang?</summary><p class="muted mb-0">Tidak. SIRKEL mengutamakan guna ulang dan perbaikan bila kondisi barang memungkinkan. Pemulihan material digunakan ketika jalur tersebut lebih sesuai.</p></details>
            <details class="card faq-card"><summary>Bagaimana mitra direkomendasikan?</summary><p class="muted mb-0">Mitra disaring berdasarkan layanan yang disetujui, kategori barang, metode penyerahan, lokasi, dan radius penjemputan. Pilihan akhir tetap di tangan warga.</p></details>
            <details class="card faq-card"><summary>Apakah wajib menggunakan AI?</summary><p class="muted mb-0">Tidak. AI hanya bantuan opsional untuk mengenali barang, merapikan catatan kondisi, atau memproses beberapa kelompok sekaligus. Anda tetap dapat menjalankan alur utama SIRKEL secara manual.</p></details>
            <details class="card faq-card"><summary>Apakah AI menentukan hasil akhir barang?</summary><p class="muted mb-0">Tidak. Saran AI membantu pengisian dan pemahaman awal. Kondisi akhir dan hasil penanganan dicatat berdasarkan proses serta pemeriksaan mitra yang menangani barang.</p></details>
        </div>
    </div>
</section>
@endsection
