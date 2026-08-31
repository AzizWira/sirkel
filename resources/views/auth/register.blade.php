@extends('layouts.auth')

@section('title', 'Daftar · SIRKEL')

@section('content')
    <h2>Buat akun warga</h2>
    <p class="muted">Buat akun untuk mulai mendaftarkan elektronik Anda.</p>

    <form id="register-form" class="stack" method="post" action="{{ route('register.store') }}">
        @csrf
        <div class="field">
            <label>Nama</label>
            <input class="input" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="field">
            <label>Email</label>
            <input class="input" type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
            <label>WhatsApp</label>
            <input id="register-wa" class="input" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="08xxxxxxxxxx"
                required>
        </div>
        <div class="field">
            <label>Password</label>
            <input class="input" type="password" name="password" required>
        </div>
        <div class="field">
            <label>Konfirmasi password</label>
            <input class="input" type="password" name="password_confirmation" required>
        </div>
        <button type="button" class="btn btn-primary btn-block"
            onclick="confirmWhatsapp('register-form','register-wa')">Daftar</button>
        <a class="btn btn-block" href="{{ route('login') }}">← Kembali ke Login</a>
    </form>

    <div class="divider"></div>
    <a class="btn btn-block" href="{{ route('auth.google') }}">
        <x-google-logo />
        <span>Daftar dengan Google</span>
    </a>
@endsection

@section('modals')
    <div id="wa-confirm" class="modal-backdrop">
        <div class="modal">
            <h3>Pastikan nomor WhatsApp benar</h3>
            <p class="muted">Nomor ini digunakan untuk komunikasi penjemputan atau penyerahan barang.</p>
            <div class="card"><strong id="wa-preview">-</strong></div>
            <div class="cluster" style="justify-content:flex-end;margin-top:16px">
                <button class="btn" onclick="closeModal('wa-confirm')">Ubah Nomor</button>
                <button class="btn btn-primary" data-confirm>Ya, Benar</button>
            </div>
        </div>
    </div>
@endsection