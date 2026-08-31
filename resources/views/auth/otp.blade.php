@extends('layouts.auth')

@section('title', 'Verifikasi Email · SIRKEL')

@section('content')
    <h2>Verifikasi email</h2>
    <p class="muted">Masukkan kode 6 digit yang dikirim ke <strong>{{ auth()->user()->email }}</strong>. Kode berlaku 10
        menit.</p>
    <form class="stack" method="post" action="{{ route('otp.verify') }}">@csrf<div class="field"><input
                class="input otp-input" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus>
        </div><button class="btn btn-primary btn-block">Verifikasi</button></form>
    <form method="post" action="{{ route('otp.resend') }}" style="margin-top:10px">@csrf<button class="btn btn-block">Kirim
            ulang OTP</button></form>
@endsection