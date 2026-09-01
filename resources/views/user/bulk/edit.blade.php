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
    <form class="form-grid" method="post" action="{{ route('user.bulk.items.update',[$session,$item]) }}" data-bulk-item-form data-item-label="{{ $asset->custom_item_name ?: $asset->category?->name }}{{ $asset->quantity>1?' ×'.$asset->quantity:'' }}">@csrf @method('put')
        <div class="field full"><label>Jenis perangkat *</label><select class="select" name="device_category_id" required data-searchable="true">
            @foreach($categories->groupBy(fn($c)=>$c->group->name) as $group=>$groupItems)
                <optgroup label="{{ $group }}">@foreach($groupItems as $c)<option value="{{ $c->id }}" data-custom="{{ $c->requiresCustomName()?1:0 }}" @selected($asset->device_category_id===$c->id)>{{ $c->name }}</option>@endforeach</optgroup>
            @endforeach
        </select></div>
        <div class="field" data-bulk-custom-name @if(!$asset->category?->requiresCustomName()) hidden @endif><label>Nama barang *</label><input class="input" name="custom_item_name" value="{{ $asset->custom_item_name }}" placeholder="Contoh: freezer mini, smart doorbell" @required($asset->category?->requiresCustomName())></div>
        <div class="field"><label>Jumlah dalam kelompok *</label><input class="input" type="number" min="1" max="999" name="quantity" value="{{ $asset->quantity }}" required></div>
        <div class="field"><label>Merek <span class="muted">(Opsional)</span></label><input class="input" name="brand" value="{{ $asset->brand }}" placeholder="Contoh: Samsung, Philips, atau kosongkan"></div>
        <div class="field"><label>Model <span class="muted">(Opsional)</span></label><input class="input" name="model_name" value="{{ $asset->model_name }}" placeholder="Contoh: Galaxy A14, HD9200, atau kosongkan"></div>
        <div class="field"><label>Perkiraan berat total (kg) <span class="muted">(Opsional)</span></label><input class="input" type="number" step="0.001" min="0" max="9999" name="estimated_weight_kg" value="{{ $asset->estimated_weight_kg }}" placeholder="Contoh: 1.5"><small>Berat akhir akan diverifikasi mitra.</small></div>
        <div class="field"><label>Sudah tidak digunakan sejak <span class="muted">(Opsional)</span></label><input class="input" type="date" name="dormant_since" value="{{ $asset->dormant_since?->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        <div class="field full"><label>Deskripsi / rincian kondisi unit *</label><textarea class="textarea" name="description" required minlength="5" maxlength="1200" placeholder="Contoh: unit 1 pintu tergores; unit 2 dispenser tidak utuh">{{ $asset->description }}</textarea><small>Contoh kelompok kabel: “1. kabel terkelupas; 2. kabel putus; 3. konektor bengkok.”</small></div>
        <div class="field full"><button class="btn btn-sm" type="submit" data-bulk-save disabled>Simpan Perubahan</button></div>
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
        <div class="field"><label>Perkiraan berat total (kg) <span class="muted">(Opsional)</span></label><input class="input" type="number" step="0.001" min="0" max="9999" name="estimated_weight_kg" placeholder="Contoh: 1.5"></div>
        <div class="field"><label>Sudah tidak digunakan sejak <span class="muted">(Opsional)</span></label><input class="input" type="date" name="dormant_since" max="{{ now()->toDateString() }}"></div>
        <div class="field full"><label>Kondisi singkat / rincian *</label><textarea class="textarea" name="description" minlength="5" maxlength="1200" required placeholder="Contoh: 2 kabel; satu terkelupas dan satu putus"></textarea></div>
        <div class="field full"><button class="btn" type="submit">Tambah ke Bulk</button></div>
    </form>
</div>
@else
<div class="alert warning mt-16">Batas 5 kelompok tercapai. Anda masih dapat mengubah jumlah unit di dalam kelompok yang sama.</div>
@endif

@if($items->isNotEmpty())
<div class="bulk-review-actions mt-16">
    <form method="post" action="{{ route('user.bulk.questionnaire.start',$session) }}" data-bulk-continue-form>@csrf<button class="btn btn-primary" type="submit"><x-icon name="sparkles" size="15"/> Lanjutkan Bulk Sekarang</button></form>
</div>
<div class="hint-box mt-12">Seluruh 1–5 kelompok akan tetap berada dalam alur Bulk AI: lanjut ke pertanyaan bersama, review rekomendasi, lalu atur penyerahan tanpa dipindahkan ke proses Standard.</div>
@endif
@endsection

@section('modals')
<div class="modal-backdrop" aria-hidden="true" data-bulk-unsaved-modal>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bulk-unsaved-title">
        <h3 id="bulk-unsaved-title">Perubahan belum disimpan</h3>
        <p class="muted">Ada perubahan pada kelompok barang yang belum disimpan.</p>
        <div class="alert warning" data-bulk-unsaved-list></div>
        <div class="form-message" data-bulk-unsaved-error aria-live="polite"></div>
        <div class="cluster">
            <button class="btn" type="button" data-bulk-unsaved-close>Batal</button>
            <button class="btn btn-primary" type="button" data-bulk-unsaved-save>Simpan & Lanjutkan</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const itemForms=[...document.querySelectorAll('[data-bulk-item-form]')];
    const continueForm=document.querySelector('[data-bulk-continue-form]');
    const modal=document.querySelector('[data-bulk-unsaved-modal]');
    const modalList=modal?.querySelector('[data-bulk-unsaved-list]');
    const modalError=modal?.querySelector('[data-bulk-unsaved-error]');
    const saveAndContinue=modal?.querySelector('[data-bulk-unsaved-save]');

    const normalizeForm=form=>{
        const select=form.querySelector('select[name="device_category_id"]');
        const wrap=form.querySelector('[data-bulk-custom-name]');
        const input=wrap?.querySelector('input[name="custom_item_name"]');
        if(!select||!wrap||!input) return;
        const isCustom=select.options[select.selectedIndex]?.dataset.custom==='1';
        wrap.hidden=!isCustom;
        input.disabled=!isCustom;
        input.required=isCustom;
        if(!isCustom) input.value='';
    };

    const fingerprint=form=>{
        const pairs=[];
        new FormData(form).forEach((value,key)=>{
            if(key==='_token'||key==='_method') return;
            pairs.push([key,String(value)]);
        });
        pairs.sort((a,b)=>a[0].localeCompare(b[0])||a[1].localeCompare(b[1]));
        return JSON.stringify(pairs);
    };

    const dirtyForms=()=>itemForms.filter(form=>form.dataset.bulkDirty==='1');
    const syncDirty=form=>{
        normalizeForm(form);
        const dirty=fingerprint(form)!==form.dataset.bulkInitial;
        form.dataset.bulkDirty=dirty?'1':'0';
        const button=form.querySelector('[data-bulk-save]');
        if(button) button.disabled=!dirty;
    };

    itemForms.forEach(form=>{
        normalizeForm(form);
        form.dataset.bulkInitial=fingerprint(form);
        form.dataset.bulkDirty='0';
        form.addEventListener('input',()=>syncDirty(form));
        form.addEventListener('change',()=>syncDirty(form));
        form.addEventListener('submit',event=>{
            syncDirty(form);
            if(form.dataset.bulkDirty!=='1') event.preventDefault();
        },true);
    });

    document.querySelectorAll('form:not([data-bulk-item-form])').forEach(form=>{
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

    const closeModal=()=>{
        if(!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('sirkel-modal-open');
    };
    const openModal=forms=>{
        if(!modal) return;
        const labels=forms.map(form=>form.dataset.itemLabel||'Kelompok barang');
        if(modalList) modalList.textContent=labels.join(', ');
        if(modalError){modalError.textContent='';modalError.dataset.tone='';}
        modal.classList.add('show');
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('sirkel-modal-open');
        saveAndContinue?.focus();
    };

    modal?.querySelectorAll('[data-bulk-unsaved-close]').forEach(button=>button.addEventListener('click',closeModal));
    modal?.addEventListener('click',event=>{if(event.target===modal) closeModal();});

    continueForm?.addEventListener('submit',event=>{
        const pending=dirtyForms();
        if(!pending.length) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        openModal(pending);
    },true);

    saveAndContinue?.addEventListener('click',async()=>{
        const pending=dirtyForms();
        if(!pending.length){closeModal();continueForm?.submit();return;}
        saveAndContinue.disabled=true;
        saveAndContinue.textContent='Menyimpan...';
        if(modalError) modalError.textContent='Menyimpan perubahan sebelum melanjutkan.';
        try{
            for(const form of pending){
                await window.axios.post(form.action,new FormData(form),{headers:{Accept:'application/json'}});
                form.dataset.bulkInitial=fingerprint(form);
                form.dataset.bulkDirty='0';
                const button=form.querySelector('[data-bulk-save]');
                if(button) button.disabled=true;
            }
            closeModal();
            continueForm?.submit();
        }catch(error){
            const errors=error?.response?.data?.errors;
            const first=errors?Object.values(errors).flat()?.[0]:null;
            if(modalError){
                modalError.textContent=first||error?.response?.data?.message||'Perubahan belum dapat disimpan. Periksa kembali data pada kelompok yang diubah.';
                modalError.dataset.tone='warning';
            }
        }finally{
            saveAndContinue.disabled=false;
            saveAndContinue.textContent='Simpan & Lanjutkan';
        }
    });
});
</script>
@endpush
