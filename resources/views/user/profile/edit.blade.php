@extends('layouts.app')

@section('title', 'Akun · SIRKEL')

@section('topbar', 'Akun')

@section('content')
    <div class="page-head">
        <div>
            <h2>Profil Warga</h2>
            <p>Email {{ $user->email }}{{ $user->google_id ? ' · terhubung Google' : '' }}</p>
        </div>
    </div>
    <form id="profile-edit" class="card form-grid" method="post" action="{{ route('user.profile.update') }}">
        @csrf @method('PUT')
        <div class="field"><label>Nama *</label><input class="input" name="name" value="{{ old('name', $user->name) }}"
                required></div>
        <div class="field"><label>WhatsApp *</label><input id="edit-wa" class="input" name="whatsapp"
                value="{{ old('whatsapp', $user->whatsapp) }}" required></div>
        <div class="field"><label>Kecamatan *</label><select id="edit-district" class="select" name="district" required>
                <option value="">Pilih</option>
                @foreach($districts as $d)
                    <option value="{{ $d->name }}" {{ old('district', $user->district) === $d->name ? 'selected' : '' }}>{{ $d->name }}
                    </option>
                @endforeach
            </select></div>
        <div class="field"><label>Kelurahan *</label><select id="edit-village" class="select" name="village"
                required></select></div>
        <div class="field full"><small class="muted">Tema dapat diubah dari menu Tampilan.</small></div>
        <div class="field full"><button type="button" class="btn btn-primary"
                onclick="confirmWhatsapp('profile-edit','edit-wa')">Simpan Profil</button></div>
    </form>
@endsection
@section('modals')
    <div id="wa-confirm" class="modal-backdrop">
        <div class="modal">
            <h3>Pastikan nomor WhatsApp benar</h3>
            <p class="muted">Nomor ini digunakan mitra terpilih untuk komunikasi penjemputan atau penyerahan barang.</p>
            <div class="card"><strong id="wa-preview">-</strong></div>
            <div class="cluster" style="justify-content:flex-end;margin-top:16px"><button class="btn"
                    onclick="closeModal('wa-confirm')">Ubah Nomor</button><button class="btn btn-primary" data-confirm>Ya,
                    Benar</button></div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => bindRegionSelect('edit-district', 'edit-village', @json(old('village', $user->village))));</script>
@endpush