@extends('layouts.auth')

@section('title', 'Masuk · SIRKEL')

@section('content')
    <h2>Masuk ke SIRKEL</h2>
    <p class="muted">Masuk untuk melanjutkan ke akun Anda.</p>

    <form class="stack" method="post" action="{{ route('login.store') }}">
        @csrf
        <div class="field">
            <label>Email</label>
            <input class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div class="field">
            <label>Password</label>
            <input class="input" type="password" name="password" required autocomplete="current-password">
        </div>
        <label class="choice">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat saya</span>
        </label>
        <button class="btn btn-primary btn-block">Masuk</button>
        <a class="text-sm" style="text-align:center;color:var(--primary);font-weight:700"
            href="{{ route('password.request') }}">Lupa password?</a>
    </form>

    <div class="divider"></div>
    <a class="btn btn-block" href="{{ route('auth.google') }}">
        <x-google-logo />
        <span>Lanjut dengan Google</span>
    </a>
    <p class="text-sm muted" style="text-align:center;margin-top:18px">Belum punya akun? <a
            style="color:var(--primary);font-weight:700" href="{{ route('register') }}">Daftar</a></p>
@endsection