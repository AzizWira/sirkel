@extends('layouts.app')
@section('title','Review Barang Bulk AI · SIRKEL')
@section('topbar','Review Bulk AI')
@section('content')
<div class="page-head"><div><span class="eyebrow">Bulk AI · PRO</span><h2>Periksa kelompok barang</h2><p>Tinjau hasil pengenalan dari foto. Anda bebas mengubah, menghapus, atau menambah kelompok sebelum lanjut.</p></div><span class="badge">{{ $items->count() }}/{{ $maxGroups }} kelompok</span></div>
@if($session->photos->isNotEmpty())
<div class="bulk-photo-strip mb-16">
@foreach($session->photos as $photo)<img src="{{ asset('storage/'.$photo->path) }}" alt="Foto Bulk {{ $loop->iteration }}">@endforeach
</div>
@endif
<div class="hint-box mb-16"><strong>Barang sejenis dapat tetap satu kelompok.</strong> Dua kulkas dapat dicatat sebagai “Kulkas ×2”, sama seperti beberapa kabel. Jika kondisi atau modelnya berbeda jauh, pisahkan agar mitra menerima informasi yang lebih jelas. Batas 5 berlaku untuk kelompok barang, bukan jumlah unit fisik.</div>

<div class="stack">
@foreach($items as $item)
@php $asset=$item->asset; @endphp
<div class="card stack bulk-item-card">
    <div class="split"><div><span class="eyebrow">Kelompok {{ $loop->iteration }} · {{ $item->source==='bulk_ai'?'Dari AI':'Manual' }}</span><h3>{{ $asset->custom_item_name ?: $asset->category?->name }}{{ $asset->quantity>1?' ×'.$asset->quantity:'' }}</h3></div><form method="post" action="{{ route('user.bulk.items.destroy',[$session,$item]) }}">@csrf @method('delete')<button class="btn btn-sm danger" type="submit" data-confirm="Hapus kelompok ini dari sesi Bulk?">Hapus</button></form></div>
    <form class="form-grid" method="post" action="{{ route('user.bulk.items.update',[$session,$item]) }}">@csrf @method('put')
        <div class="field full"><label>Jenis perangkat *</label><select class="select" name="device_category_id" required data-searchable="true">
            @foreach($categories->groupBy(fn($c)=>$c->group->name) as $group=>$groupItems)
                <optgroup label="{{ $group }}">@foreach($groupItems as $c)<option value="{{ $c->id }}" data-custom="{{ $c->requiresCustomName()?1:0 }}" @selected($asset->device_category_id===$c->id)>{{ $c->name }}</option>@endforeach</optgroup>
            @endforeach
        </select></div>
        <div class="field" data-bulk-custom-name @if(!$asset->category?->requiresCustomName()) hidden @endif><label>Nama barang *</label><input class="input" name="custom_item_name" value="{{ $asset->custom_item_name }}" placeholder="Contoh: freezer mini, smart doorbell" @required($asset->category?->requiresCustomName())></div>
        <div class="field"><label>Jumlah dalam kelompok *</label><input class="input" type="number" min="1" max="999" name="quantity" value="{{ $asset->quantity }}" required></div>
        <div class="field"><label>Merek <span class="muted">(Opsional)</span></label><input class="input" name="brand" value="{{ $asset->brand }}" placeholder="Contoh: Samsung, Philips, atau kosongkan"></div>
        <div class="field"><label>Model <span class="muted">(Opsional)</span></label><input class="input" name="model_name" value="{{ $asset->model_name }}" placeholder="Contoh: Galaxy A14, HD9200, atau kosongkan"></div>
        <div class="field full"><label>Deskripsi / rincian kondisi unit *</label><textarea class="textarea" name="description" required minlength="5" maxlength="1200" placeholder="Contoh: unit 1 pintu tergores; unit 2 dispenser tidak utuh">{{ $asset->description }}</textarea><small>Contoh kelompok kabel: “1. kabel terkelupas; 2. kabel putus; 3. konektor bengkok.”</small></div>
        <div class="field full"><button class="btn btn-sm" type="submit">Simpan Perubahan</button></div>
    </form>
</div>
@endforeach
</div>

@if($items->count() < $maxGroups)
<div class="card stack mt-16">
    <div><h3>+ Tambah Barang Manual</h3><p class="muted mb-0">Gunakan jika ada barang yang terlewat AI atau tidak terlihat jelas pada foto.</p></div>
    <form class="form-grid" method="post" action="{{ route('user.bulk.items.store',$session) }}">@csrf
        <div class="field full"><label>Jenis perangkat *</label><select class="select" name="device_category_id" required data-searchable="true"><option value="">Pilih jenis barang</option>@foreach($categories->groupBy(fn($c)=>$c->group->name) as $group=>$groupItems)<optgroup label="{{ $group }}">@foreach($groupItems as $c)<option value="{{ $c->id }}" data-custom="{{ $c->requiresCustomName()?1:0 }}">{{ $c->name }}</option>@endforeach</optgroup>@endforeach</select></div>
        <div class="field" data-bulk-custom-name hidden><label>Nama barang *</label><input class="input" name="custom_item_name" disabled placeholder="Contoh: freezer mini, smart doorbell"></div>
        <div class="field"><label>Jumlah *</label><input class="input" type="number" min="1" max="999" name="quantity" value="1" required></div>
        <div class="field"><label>Merek <span class="muted">(Opsional)</span></label><input class="input" name="brand" placeholder="Contoh: Samsung, Philips, atau kosongkan"></div>
        <div class="field"><label>Model <span class="muted">(Opsional)</span></label><input class="input" name="model_name" placeholder="Contoh: Galaxy A14, HD9200, atau kosongkan"></div>
        <div class="field full"><label>Kondisi singkat / rincian *</label><textarea class="textarea" name="description" minlength="5" maxlength="1200" required placeholder="Contoh: 2 kabel; satu terkelupas dan satu putus"></textarea></div>
        <div class="field full"><button class="btn" type="submit">Tambah ke Bulk</button></div>
    </form>
</div>
@else
<div class="alert warning mt-16">Batas 5 kelompok tercapai. Anda masih dapat mengubah jumlah unit di dalam kelompok yang sama.</div>
@endif

@if($items->isNotEmpty())
<div class="bulk-review-actions mt-16">
    <form method="post" action="{{ route('user.bulk.cart',$session) }}">@csrf<button class="btn" type="submit">Simpan Semua ke Keranjang</button></form>
    <form method="post" action="{{ route('user.bulk.questionnaire.start',$session) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="sparkles" size="15"/> Lanjutkan Bulk Sekarang</button></form>
</div>
<div class="hint-box mt-12">Jika disimpan ke Keranjang, Anda dapat memproses maksimal 3 kelompok seperti biasa. Jika lanjut sekarang, seluruh 1–5 kelompok akan dicek bersama dengan pertanyaan yang disesuaikan agar tidak berulang.</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('form').forEach(form=>{
        const select=form.querySelector('select[name="device_category_id"]');
        const wrap=form.querySelector('[data-bulk-custom-name]');
        const input=wrap?.querySelector('input[name="custom_item_name"]');
        if(!select||!wrap||!input) return;
        const sync=()=>{
            const isCustom=select.options[select.selectedIndex]?.dataset.custom==='1';
            wrap.hidden=!isCustom; input.disabled=!isCustom; input.required=isCustom;
            if(!isCustom) input.value='';
        };
        select.addEventListener('change',sync); sync();
    });
});
</script>
@endpush
