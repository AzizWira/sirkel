@extends('layouts.auth')

@section('title', 'Lupa Password · SIRKEL')

@section('content')
    <h2>Lupa password</h2>
    <p class="muted">Masukkan email akun. Tautan reset akan dikirim melalui email.</p>
    <form class="stack" method="post" action="{{ route('password.email') }}">@csrf<div class="field">
            <label>Email</label><input class="input" type="email" name="email" value="{{ old('email') }}" required
                autofocus></div><button class="btn btn-primary btn-block">Kirim Tautan Reset</button></form>
    <p class="text-sm" style="text-align:center;margin-top:18px"><a href="{{ route('login') }}">← Kembali ke login</a></p>
@endsection