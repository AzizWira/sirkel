@extends('layouts.auth')

@section('title', 'Lengkapi Profil · SIRKEL')

@section('content')
    <h2>Lengkapi profil</h2>
    <p class="muted">Lengkapi data berikut sebelum menggunakan SIRKEL.</p>
    <form id="profile-form" class="stack" method="post" action="{{ route('profile.complete.store') }}">@csrf<div
            class="field"><label>Nama</label><input class="input" name="name" value="{{ old('name', auth()->user()->name) }}"
                required></div>
        <div class="field"><label>WhatsApp</label><input id="profile-wa" class="input" name="whatsapp"
                value="{{ old('whatsapp', auth()->user()->whatsapp) }}" placeholder="08xxxxxxxxxx" required></div>
        <div class="field"><label>Kecamatan</label><select id="profile-district" class="select" name="district" required>
                <option value="">Pilih kecamatan</option>
                @foreach($districts as $d)
                    <option value="{{ $d->name }}" {{ old('district', auth()->user()->district) === $d->name ? 'selected' : '' }}>
                        {{ $d->name }}</option>
                @endforeach
            </select></div>
        <div class="field"><label>Kelurahan</label><select id="profile-village" class="select" name="village" required>
                <option value="">Pilih kecamatan dulu</option>
            </select></div><button type="button" class="btn btn-primary btn-block"
            onclick="confirmWhatsapp('profile-form','profile-wa')">Simpan & Lanjut</button>
    </form>
@endsection
@section('modals')
    <div id="wa-confirm" class="modal-backdrop">
        <div class="modal">
            <h3>Pastikan nomor WhatsApp benar</h3>
            <p class="muted">Nomor ini dibagikan kepada mitra setelah permintaan Anda diterima.</p>
            <div class="card"><strong id="wa-preview">-</strong></div>
            <div class="cluster" style="justify-content:flex-end;margin-top:16px"><button class="btn"
                    onclick="closeModal('wa-confirm')">Ubah Nomor</button><button class="btn btn-primary" data-confirm>Ya,
                    Benar</button></div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => bindRegionSelect('profile-district', 'profile-village', @json(old('village', auth()->user()->village))));</script>
@endpush