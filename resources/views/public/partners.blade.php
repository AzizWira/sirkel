@extends('layouts.public')

@section('title','Mitra Pengelolaan E-Waste Surabaya | SIRKEL')
@section('meta_description','Temukan mitra SIRKEL di Surabaya untuk perbaikan, guna ulang, donasi, pemulihan material, dan penanganan khusus. Lihat lokasi serta radius penjemputan.')

@section('content')
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Mitra SIRKEL</div>
                <h1 style="font-size:48px">Temukan mitra elektronik di Surabaya.</h1>
                <p class="lead">Lihat layanan, lokasi, dan radius penjemputan. Setelah barang didaftarkan, SIRKEL membantu menyaring mitra yang sesuai dengan kebutuhan penanganannya.</p>
            </div>
        </div>

        <div class="grid-3">
            @forelse($partners as $p)
                <article class="card">
                    <div class="split"><h3>{{ $p->business_name }}</h3><span class="badge success">Terverifikasi</span></div>
                    <p class="muted">{{ collect([$p->village, $p->district, 'Surabaya'])->filter()->implode(', ') }}</p>
                    <div class="cluster">
                        @foreach($p->capabilities->where('status','approved') as $c)
                            <span class="tag">{{ \App\Support\SirkelUi::label($c->capability) }}</span>
                        @endforeach
                    </div>
                    <div class="divider"></div>
                    <p class="text-sm mb-0">Radius penjemputan <strong>{{ $p->pickup_radius_km }} km</strong></p>
                </article>
            @empty
                <div class="card empty">Belum ada mitra yang tersedia.</div>
            @endforelse
        </div>

        <div style="margin-top:20px">{{ $partners->links() }}</div>
        <p class="text-sm muted" style="margin-top:18px">Status Terverifikasi menunjukkan data profil dan layanan mitra telah ditinjau oleh SIRKEL.</p>
    </div>
</section>
@endsection
