@extends('layouts.auth')

@section('title', 'Pilih Akses · SIRKEL')

@section('content')
    <div class="access-choice-head">
        <h2>Pilih cara menggunakan SIRKEL</h2>
        <p class="muted">Pilih cara Anda ingin menggunakan SIRKEL kali ini.</p>
    </div>

    <form class="stack" method="post" action="{{ route('access.choose.store') }}">
        @csrf

        @if(in_array('user', $roles, true))
            <button class="access-choice-card" type="submit" name="access" value="user">
                <span class="access-choice-icon"><x-icon name="profile" /></span>
                <span>
                    <strong>Masuk sebagai Warga</strong>
                    <small>Daftarkan barang, pilih mitra, dan pantau perjalanan barang Anda.</small>
                </span>
                <span aria-hidden="true">›</span>
            </button>
        @endif

        @if(in_array('partner', $roles, true))
            <button class="access-choice-card" type="submit" name="access" value="partner">
                <span class="access-choice-icon"><x-icon name="partners" /></span>
                <span>
                    <strong>Masuk sebagai Mitra</strong>
                    <small>Kelola permintaan masuk, penanganan barang, dan pengalihan antar-mitra.</small>
                </span>
                <span aria-hidden="true">›</span>
            </button>
        @endif
    </form>

    <form method="post" action="{{ route('logout') }}" class="mt-16">
        @csrf
        <button class="btn btn-block" type="submit">Keluar</button>
    </form>
@endsection