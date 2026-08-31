@extends('layouts.auth')

@section('title', 'Reset Password · SIRKEL')

@section('content')
    <h2>Buat password baru</h2>
    <form class="stack" method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token"
            value="{{ $token }}">
        <div class="field"><label>Email</label><input class="input" type="email" name="email"
                value="{{ old('email', $email) }}" required></div>
        <div class="field"><label>Password baru</label><input class="input" type="password" name="password" required></div>
        <div class="field"><label>Konfirmasi password</label><input class="input" type="password"
                name="password_confirmation" required></div><button class="btn btn-primary btn-block">Simpan
            Password</button>
    </form>
@endsection