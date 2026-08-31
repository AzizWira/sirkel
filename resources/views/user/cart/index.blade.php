@extends('layouts.app')

@section('title','Keranjang Elektronik · SIRKEL')
@section('topbar','Keranjang Elektronik')

@section('content')
<div class="page-head">
    <div>
        <h2>Keranjang Elektronik</h2>
        <p>Simpan barang sebanyak yang diperlukan. Saat ingin cek kondisi, pilih maksimal 3 kelompok sekaligus.</p>
    </div>
    <div class="cluster">
        <a class="btn" href="{{ route('user.assets.create') }}"><x-icon name="plus"/> Tambah Barang</a>
        <a class="btn btn-primary" href="{{ route('user.bulk.create') }}"><x-icon name="sparkles"/> Bulk AI <span class="badge">PRO</span></a>
    </div>
</div>

@if($sessions->isNotEmpty())
<div class="card stack mb-16">
    <div><h3>Proses yang dapat dilanjutkan</h3><p class="muted mb-0">Jawaban yang sudah tersimpan tidak hilang ketika Anda keluar dari proses.</p></div>
    @foreach($sessions as $session)
        @php
            $resume = $session->mode === \App\Models\IntakeSession::MODE_BULK_AI
                ? ($session->status === \App\Models\IntakeSession::STATUS_DRAFT
                    ? route('user.bulk.edit',$session)
                    : ($session->status === \App\Models\IntakeSession::STATUS_QUESTIONNAIRE
                        ? route('user.bulk.questionnaire',$session)
                        : route('user.intake.review',$session)))
                : ($session->status === \App\Models\IntakeSession::STATUS_REVIEW
                    ? route('user.intake.review',$session)
                    : route('user.intake.standard.show',$session));
        @endphp
        <div class="cart-session-row">
            <div>
                <strong>{{ $session->mode==='bulk_ai' ? 'Bulk AI' : 'Cek kondisi biasa' }}</strong>
                <div class="muted text-sm">{{ $session->items->count() }} kelompok · {{ \App\Support\SirkelUi::label($session->status) }}</div>
            </div>
            <a class="btn btn-sm" href="{{ $resume }}">Lanjutkan</a>
        </div>
    @endforeach
</div>
@endif

<form method="post" action="{{ route('user.cart.process') }}" data-cart-process-form>
@csrf
<div class="card stack">
    <div class="split cart-selection-head">
        <div>
            <h3>Barang tersimpan</h3>
            <p class="muted mb-0">Keranjang tidak memiliki batas jumlah. Batas 3 hanya berlaku pada pilihan yang diproses sekarang.</p>
        </div>
        <div class="cart-selection-counter"><strong data-cart-selected-count>0</strong>/3 dipilih</div>
    </div>

    @forelse($assets as $asset)
        <label class="cart-item-card">
            <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" data-cart-item-checkbox>
            <div class="cart-item-photo">
                @if($asset->photos->first())
                    <img src="{{ asset('storage/'.$asset->photos->first()->path) }}" alt="Foto {{ $asset->custom_item_name ?: $asset->category?->name }}">
                @else
                    <x-icon name="box" size="28"/>
                @endif
            </div>
            <div class="cart-item-copy">
                <strong>{{ $asset->custom_item_name ?: $asset->category?->name }}</strong>
                <span>{{ $asset->quantity > 1 ? '×'.$asset->quantity.' · Kelompok barang' : 'Satuan' }}</span>
                <small>{{ \Illuminate\Support\Str::limit($asset->description,120) }}</small>
            </div>
            <span class="cart-item-check">✓</span>
        </label>
    @empty
        <div class="empty">
            <strong>Keranjang masih kosong.</strong><br>
            Daftarkan barang satu per satu atau gunakan Bulk AI untuk beberapa kelompok sekaligus.
        </div>
    @endforelse

    @if($assets->isNotEmpty())
        <div class="cart-process-bar">
            <div>
                <strong>Pilih 1–3 kelompok untuk cek kondisi.</strong>
                <small class="muted">Barang yang tidak dipilih tetap aman di Keranjang.</small>
            </div>
            <button class="btn btn-primary" type="submit" data-cart-process-button disabled>Proses Barang Terpilih</button>
        </div>
    @endif
</div>
</form>
@endsection
