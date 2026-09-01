@extends('layouts.app')

@section('title','Daftarkan Elektronik · SIRKEL')

@section('topbar','Tambah Barang')

@section('content')
@php
    $oldCategory = $categories->firstWhere('id', (int) old('device_category_id'));
    $showCustomName = (bool) $oldCategory?->requiresCustomName();
    $showQuantity = old('tracking_type', 'individual') === 'batch';
@endphp
<div class="page-head"><div><h2>Daftarkan Elektronik</h2><p>Simpan barang ke Keranjang, lalu pilih maksimal 3 kelompok saat ingin memproses cek kondisi.</p></div><div class="cluster"><a class="btn" href="{{ route('user.cart.index') }}">Keranjang</a><a class="btn btn-primary" href="{{ route('user.bulk.create') }}"><x-icon name="sparkles" size="15"/> Bulk AI <span class="badge">PRO</span></a></div></div>
<div class="hint-box mb-16"><strong>Punya beberapa jenis elektronik sekaligus?</strong> Gunakan Bulk AI untuk mengenali dan mengelompokkan maksimal 5 jenis barang dalam satu sesi. Pendaftaran biasa hanya untuk satu jenis barang, tetapi tetap boleh memakai 1–3 foto dari sudut berbeda.</div>
<div class="stepper"><div class="step active"><span class="dot">1</span>Barang</div><div class="step"><span class="dot">2</span>Kondisi</div><div class="step"><span class="dot">3</span>Rekomendasi</div><div class="step"><span class="dot">4</span>Penyerahan</div><div class="step"><span class="dot">5</span>Mitra</div></div>
<form id="asset-create-form" class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('user.assets.store') }}">
@csrf
<input type="hidden" name="save_to_cart" value="1">
<input type="hidden" name="photo_scope_status" value="unknown" data-asset-photo-scope-status>
<div class="field full">
<label>Foto satu jenis barang * — 1–3 foto</label>
<div class="asset-photo-picker" data-asset-photo-picker data-ai-url="{{ route('user.assets.ai-draft') }}" data-max-files="3" data-max-size-mb="5" data-ai-quota-remaining="{{ $aiQuota['remaining'] }}" data-ai-topup-url="{{ route('user.ai-quota.index') }}">
    <input id="asset-photos" class="asset-photo-input-native" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-asset-photo-input>
    <input class="asset-photo-input-native" type="file" accept="image/jpeg,image/png,image/webp" multiple tabindex="-1" aria-hidden="true" data-asset-gallery-input>
    <input class="asset-photo-input-native" type="file" accept="image/*" capture="environment" tabindex="-1" aria-hidden="true" data-asset-camera-fallback>
    <div class="asset-photo-actions">
        <button class="btn" type="button" data-asset-gallery><x-icon name="image"/> Pilih Foto</button>
        <button class="btn" type="button" data-asset-camera><x-icon name="camera"/> Kamera</button>
    </div>
    <small>Semua foto harus menampilkan <b>satu jenis barang yang sama</b> (boleh dari sudut berbeda atau beberapa unit sejenis). Untuk jenis barang berbeda, gunakan Bulk AI. Foto <b>tidak dikirim ke AI</b> kecuali Anda menekan “Proses dengan AI”. JPG, PNG, atau WebP; maksimal 5 MB per foto.</small>
    <div class="form-message" data-asset-photo-status aria-live="polite"></div>
    <div id="asset-photo-preview" class="photo-preview asset-photo-preview" data-asset-photo-preview></div>

    <div class="asset-ai-intake">
        <div>
            <strong>Bantu isi dari foto</strong>
            <p class="muted mb-0">Opsional. AI hanya menyarankan jenis barang, nama jika masuk kategori Lainnya, tipe/jumlah yang terlihat, dan kondisi visual singkat.</p>
            <small>Kuota tersedia: <strong data-asset-ai-quota-label>{{ number_format($aiQuota['remaining']) }}×</strong> · <a href="{{ route('user.ai-quota.index') }}">Lihat Kuota / Top Up</a></small>
        </div>
        <button class="btn btn-primary" type="button" data-asset-ai-process disabled>{{ $aiQuota['exhausted'] ? 'Kuota habis' : 'Proses dengan AI' }}</button>
    </div>
    <div class="form-message" data-asset-ai-status aria-live="polite">{{ $aiQuota['exhausted'] ? 'Kuota Pengenalan Barang sudah habis. Tambah kuota jika ingin memakai bantuan foto.' : 'Belum meminta bantuan AI untuk foto ini.' }}</div>
</div>
</div>
<div class="field full">
    <div class="hint-box">Mulai dari foto jika ingin dibantu AI, atau lanjut isi form secara manual. Foto hanya dianalisis setelah Anda menekan <strong>Proses dengan AI</strong>.</div>
</div>
<div class="field full"><label>Jenis perangkat *</label><select class="select" name="device_category_id" id="device-category" data-searchable="true" data-search-placeholder="Cari jenis elektronik..." required><option value="">Pilih jenis barang</option>
@foreach($categories->groupBy(fn($c)=>$c->group->name) as $group=>$items)
<optgroup label="{{ $group }}">
@foreach($items as $c)
<option value="{{ $c->id }}" data-code="{{ $c->code }}" data-custom="{{ $c->requiresCustomName()?1:0 }}" data-batch="{{ $c->supports_batch?1:0 }}" {{ old('device_category_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
@endforeach
</optgroup>
@endforeach
</select><small>Jika nama barang belum ada, pilih kategori <b>Lainnya</b> pada kelompok yang paling mendekati. Jika benar-benar tidak tahu kelompoknya, pilih <b>Elektronik Lainnya / Belum Tahu Kategorinya</b>.</small></div>
<div id="custom-name-field" class="field full"
@unless($showCustomName)
 hidden
@endunless
><label>Nama barang *</label><input id="custom-item-name" class="input" name="custom_item_name" value="{{ old('custom_item_name') }}" placeholder="Contoh: mesin pembuat es, freezer mini, smart doorbell"
@unless($showCustomName)
 disabled
@endunless

@if($showCustomName)
 required
@endif
><small>Gunakan nama yang mudah dikenali.</small></div>
<div class="field"><label>Tipe pencatatan *</label><select id="tracking-type" class="select" name="tracking_type" required><option value="individual" {{ old('tracking_type','individual')==='individual'?'selected':'' }}>Satuan — satu barang</option><option value="batch" {{ old('tracking_type')==='batch'?'selected':'' }}>Kelompok barang — kategori & kondisi sama</option></select><small id="batch-help">Pengelompokan hanya tersedia untuk kategori yang mendukung pencatatan beberapa barang sejenis.</small></div>
<div id="quantity-field" class="field"
@unless($showQuantity)
 hidden
@endunless
><label>Jumlah barang *</label><input id="asset-quantity" class="input" type="number" min="2" max="999" name="quantity" value="{{ old('quantity',$showQuantity?2:1) }}" required><small>Satu kelompok wajib berisi barang dengan kategori dan kondisi yang sama.</small></div>
<div class="field"><label>Merek</label><input class="input" name="brand" value="{{ old('brand') }}" placeholder="Opsional"></div>
<div class="field"><label>Model</label><input class="input" name="model_name" value="{{ old('model_name') }}" placeholder="Opsional"></div>
<div class="field"><label>Perkiraan berat (kg) <span class="muted">(Opsional)</span></label><input class="input" type="number" step="0.001" min="0" max="9999" name="estimated_weight_kg" value="{{ old('estimated_weight_kg') }}"><small>Berat akhir akan dicatat oleh mitra.</small></div>
<div class="field"><label>Sudah tidak digunakan sejak <span class="muted">(Opsional)</span></label><input class="input" type="date" name="dormant_since" value="{{ old('dormant_since') }}" max="{{ now()->toDateString() }}"></div>
<div class="field"><label>Kecamatan asal *</label><select id="asset-district" class="select" name="origin_district" required><option value="">Pilih kecamatan</option>
@foreach($districts as $d)
<option value="{{ $d->name }}" {{ old('origin_district',auth()->user()->district)===$d->name?'selected':'' }}>{{ $d->name }}</option>
@endforeach
</select></div>
<div class="field"><label>Kelurahan asal *</label><select id="asset-village" class="select" name="origin_village" required><option value="">Pilih kecamatan dulu</option></select></div>
<div class="field full"><label>Kondisi singkat / alasan tidak digunakan *</label><textarea class="textarea" name="description" required minlength="10" maxlength="1200" placeholder="Contoh: pemanggang menyala tetapi tidak lagi panas">{{ old('description') }}</textarea><small>Ceritakan gejala atau alasan barang sudah tidak dipakai. Tidak perlu istilah teknis.</small></div>
<div class="field full"><button class="btn btn-primary">Simpan ke Keranjang</button><small>Barang tidak langsung diproses. Dari Keranjang Anda dapat memilih maksimal 3 kelompok untuk satu kali cek kondisi.</small></div>
</form>
@endsection

@section('modals')
<div id="asset-camera-modal" class="modal-backdrop asset-media-modal" aria-hidden="true" data-asset-camera-modal>
    <div class="modal asset-camera-card" role="dialog" aria-modal="true" aria-labelledby="asset-camera-title">
        <div class="asset-modal-head">
            <div><h3 id="asset-camera-title">Ambil Foto</h3><p class="muted mb-0">Kamera berjalan langsung di browser. Foto tetap bisa Anda hapus sebelum menyimpan.</p></div>
            <button class="icon-button" type="button" aria-label="Tutup kamera" data-asset-camera-close>×</button>
        </div>
        <div class="asset-camera-stage"><video autoplay playsinline muted data-asset-camera-video></video></div>
        <p class="muted" data-asset-camera-state>Menyiapkan kamera...</p>
        <div class="cluster asset-modal-actions">
            <button class="btn" type="button" data-asset-camera-close>Batal</button>
            <button class="btn" type="button" data-asset-camera-native>Buka Kamera Ponsel</button>
            <button class="btn btn-primary" type="button" data-asset-camera-capture disabled>Ambil Foto</button>
        </div>
    </div>
</div>

<div id="asset-photo-scope-modal" class="modal-backdrop asset-media-modal" aria-hidden="true" data-asset-photo-scope-modal>
    <div class="modal asset-ai-suggestion-card" role="dialog" aria-modal="true" aria-labelledby="asset-photo-scope-title">
        <div class="asset-modal-head">
            <div><h3 id="asset-photo-scope-title">Satu jenis barang saja</h3><p class="muted mb-0">Pendaftaran biasa boleh memakai beberapa foto, tetapi semuanya harus untuk barang atau kelompok sejenis yang sama.</p></div>
            <button class="icon-button" type="button" aria-label="Tutup pemberitahuan" data-asset-photo-scope-close>×</button>
        </div>
        <div class="hint-box">
            <strong>Contoh yang boleh:</strong> tiga foto kabel charger yang sama/sejenis dari sudut berbeda.<br>
            <strong>Untuk jenis berbeda:</strong> misalnya kulkas + mesin cuci + microwave, gunakan Bulk AI agar setiap barang dibuat sebagai kelompok terpisah.
        </div>
        <div class="cluster asset-modal-actions">
            <button class="btn" type="button" data-asset-photo-scope-close>Lanjut dengan foto ini</button>
            <a class="btn btn-primary" href="{{ route('user.bulk.create') }}">Gunakan Bulk AI</a>
        </div>
    </div>
</div>

<div id="asset-ai-suggestion-modal" class="modal-backdrop asset-media-modal" aria-hidden="true" data-asset-ai-modal>
    <div class="modal asset-ai-suggestion-card" role="dialog" aria-modal="true" aria-labelledby="asset-ai-title">
        <div class="asset-modal-head">
            <div><h3 id="asset-ai-title">Saran dari Foto</h3><p class="muted mb-0">Pilih bagian saran yang ingin digunakan untuk melengkapi data barang.</p></div>
            <button class="icon-button" type="button" aria-label="Tutup saran AI" data-asset-ai-close>×</button>
        </div>
        <div class="hint-box asset-ai-difference" data-asset-ai-difference hidden></div>

        <div class="asset-ai-rejection" data-asset-ai-rejection hidden>
            <div class="asset-ai-rejection-icon" aria-hidden="true">!</div>
            <div>
                <strong data-asset-ai-rejection-title>Foto belum sesuai untuk bantuan AI</strong>
                <p data-asset-ai-rejection-reason>Gunakan foto barang elektronik fisik yang ingin didaftarkan.</p>
                <small data-asset-ai-rejection-detected></small>
            </div>
        </div>

        <div class="asset-ai-result-grid" data-asset-ai-result-grid>
            <div class="asset-ai-result-card" data-ai-field-card="identity">
                <div class="asset-ai-result-head">
                    <span>Jenis barang</span>
                    <button class="asset-ai-pick" type="button" data-ai-field-toggle="identity" aria-pressed="false">
                        <b data-ai-field-status="identity">Belum dipilih</b>
                        <em data-ai-field-action="identity">Pilih</em>
                    </button>
                </div>
                <strong data-ai-result-name>—</strong>
                <small data-ai-result-category></small>
            </div>
            <div class="asset-ai-result-card" data-ai-field-card="tracking">
                <div class="asset-ai-result-head">
                    <span>Tipe pencatatan</span>
                    <button class="asset-ai-pick" type="button" data-ai-field-toggle="tracking" aria-pressed="false">
                        <b data-ai-field-status="tracking">Belum dipilih</b>
                        <em data-ai-field-action="tracking">Pilih</em>
                    </button>
                </div>
                <strong data-ai-result-tracking>—</strong>
                <small data-ai-result-quantity></small>
            </div>
            <div class="asset-ai-result-card full" data-ai-field-card="description">
                <div class="asset-ai-result-head">
                    <span>Kondisi visual singkat</span>
                    <button class="asset-ai-pick" type="button" data-ai-field-toggle="description" aria-pressed="false">
                        <b data-ai-field-status="description">Belum dipilih</b>
                        <em data-ai-field-action="description">Pilih</em>
                    </button>
                </div>
                <p data-ai-result-description>—</p>
            </div>
        </div>
        <p class="muted text-sm" data-asset-ai-review-note>Pilih satu, beberapa, atau semua saran yang sesuai. Periksa kembali hasilnya sebelum melanjutkan.</p>
        <div class="asset-ai-select-actions" data-asset-ai-select-actions>
            <button class="btn btn-sm" type="button" data-asset-ai-select-all>Pilih semua</button>
            <span class="muted text-sm" data-asset-ai-selected-count>Belum ada saran dipilih.</span>
        </div>
        <div class="cluster asset-modal-actions">
            <button class="btn" type="button" data-asset-ai-keep>Tetap gunakan data saya</button>
            <button class="btn" type="button" data-asset-ai-replace hidden>Pilih foto lain</button>
            <a class="btn btn-primary" href="{{ route('user.bulk.create') }}" data-asset-ai-bulk hidden>Gunakan Bulk AI</a>
            <button class="btn btn-primary" type="button" data-asset-ai-apply disabled>Gunakan yang dipilih</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')<script>
document.addEventListener('DOMContentLoaded',()=>{
    bindRegionSelect('asset-district','asset-village',@json(old('origin_village',auth()->user()->village)));
    const category=document.getElementById('device-category');
    const tracking=document.getElementById('tracking-type');
    const quantity=document.getElementById('asset-quantity');
    const quantityField=document.getElementById('quantity-field');
    const customField=document.getElementById('custom-name-field');
    const customInput=document.getElementById('custom-item-name');
    const batchHelp=document.getElementById('batch-help');

    function syncConditionalFields(){
        const selected=category.options[category.selectedIndex];
        const supportsBatch=selected?.dataset.batch==='1';
        const isOther=selected?.dataset.custom==='1';
        const batchOption=[...tracking.options].find(option=>option.value==='batch');

        if(batchOption) batchOption.disabled=!supportsBatch;
        if(!supportsBatch && tracking.value==='batch') tracking.value='individual';

        customField.hidden=!isOther;
        customInput.disabled=!isOther;
        customInput.required=isOther;
        if(!isOther) customInput.value='';

        const isBatch=tracking.value==='batch';
        quantityField.hidden=!isBatch;
        quantity.min=isBatch?'2':'1';
        if(!isBatch) quantity.value=1;
        else if(Number(quantity.value)<2) quantity.value=2;

        batchHelp.textContent=supportsBatch
            ? 'Kategori ini dapat dicatat sebagai satu barang atau satu kelompok barang sejenis.'
            : 'Kategori ini dicatat sebagai satu barang.';
    }

    category.addEventListener('change',syncConditionalFields);
    tracking.addEventListener('change',syncConditionalFields);
    syncConditionalFields();
});
</script>@endpush
