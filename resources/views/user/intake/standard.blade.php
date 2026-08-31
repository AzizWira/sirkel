@extends('layouts.app')

@section('title','Cek Kondisi Keranjang · SIRKEL')
@section('topbar','Cek Kondisi')

@section('content')
@php
    $questions = ($template?->questions ?? collect())->sortBy(fn($question) => $question->code === 'notes' ? 9999 : $question->sort_order)->values();
    $dataBearing = $questions->contains(fn($question) => $question->code === 'personal_data');
@endphp
<div class="page-head">
    <div>
        <h2>Cek Kondisi · Barang {{ $position }} dari {{ $total }}</h2>
        <p>{{ $asset->custom_item_name ?: $asset->category->name }}{{ $asset->quantity>1?' ×'.$asset->quantity:'' }} · jawaban tersimpan otomatis.</p>
    </div>
    <button class="btn" type="submit" form="standard-intake-form" formaction="{{ route('user.intake.standard.pause',[$session,$item]) }}" formnovalidate>Simpan & Keluar</button>
</div>

<div class="intake-progress-items">
@foreach($items as $loopItem)
    <div class="intake-progress-item {{ $loopItem->assessment_completed_at?'done':($loopItem->id===$item->id?'active':'') }}">
        <span>{{ $loop->iteration }}</span>
        <div><strong>{{ $loopItem->asset->custom_item_name ?: $loopItem->asset->category?->name }}</strong><small>{{ $loopItem->assessment_completed_at?'Selesai':($loopItem->id===$item->id?'Sedang diisi':'Menunggu') }}</small></div>
    </div>
@endforeach
</div>

<form id="standard-intake-form" class="stack mt-16" method="post" action="{{ route('user.intake.standard.complete-item',[$session,$item]) }}"
      data-citizen-assessment-form data-intake-autosave-form
      data-autosave-url="{{ route('user.intake.standard.autosave',[$session,$item]) }}"
      data-ai-description-url="{{ route('user.assets.ai-condition-description',$asset) }}"
      data-ai-description-quota="{{ $aiDescriptionQuota['remaining'] }}"
      data-ai-topup-url="{{ route('user.ai-quota.index') }}">
@csrf
<div class="card">
    <div class="split"><div><h3>Pertanyaan singkat</h3><p class="muted mb-0">Jawab yang benar-benar Anda ketahui. Tombol bantuan menjelaskan maksud pertanyaan dan tidak memakai kuota AI.</p></div><small class="autosave-state" data-autosave-state>Belum ada perubahan</small></div>
    <div class="stack mt-16">
    @forelse($questions as $q)
        @php $saved = old('answers.'.$q->code, $answers[$q->code] ?? null); @endphp
        <div class="field assessment-question" data-assessment-question data-question-code="{{ $q->code }}" data-question-text="{{ $q->text }}" data-question-type="{{ $q->type }}" data-question-required="{{ $q->required?'1':'0' }}" data-help-text="{{ $q->help_text }}" data-validation-field="answers.{{ $q->code }}">
            <label>{{ $q->text }} @if($q->required)*@endif</label>
            @if($q->type==='text')
                <textarea class="textarea" name="answers[{{ $q->code }}]" @required($q->required) @if($q->code==='notes') data-condition-notes @endif>{{ is_array($saved)?'':$saved }}</textarea>
                @if($q->code==='notes')
                    <div class="condition-ai-assist">
                        <div><strong>Bantu susun catatan dari jawaban</strong><small>Opsional. Kuota tersedia: <strong data-condition-ai-quota-label>{{ number_format($aiDescriptionQuota['remaining']) }}×</strong> · <a href="{{ route('user.ai-quota.index') }}">Kuota AI</a></small></div>
                        <button type="button" class="btn btn-sm" data-generate-condition-description disabled><x-icon name="sparkles" size="15"/> Buat deskripsi dengan AI</button>
                    </div>
                    <div class="form-message" data-condition-ai-status>Lengkapi semua pertanyaan wajib untuk mengaktifkan bantuan AI.</div>
                @endif
            @else
                <div class="choice-list">
                @foreach($q->options as $o)
                    @php
                        $isMulti=$q->type==='multi';
                        $checked=$isMulti?in_array((string)$o->value,array_map('strval',(array)$saved),true):(string)$saved===(string)$o->value;
                    @endphp
                    <label class="choice"><input type="{{ $isMulti?'checkbox':'radio' }}" name="answers[{{ $q->code }}]{{ $isMulti?'[]':'' }}" value="{{ $o->value }}" @checked($checked) @required($q->required&&!$isMulti)><span>{{ $o->label }}</span></label>
                @endforeach
                </div>
            @endif
            <div class="question-help-actions"><button type="button" class="btn btn-sm question-help-btn" data-open-question-help>Bantuan pertanyaan</button></div>
        </div>
    @empty
        <input type="hidden" name="answers[power_status]" value="unknown"><input type="hidden" name="answers[damage_level]" value="unknown">
    @endforelse
    </div>
</div>

@if($dataBearing)
<div class="card"><h3>Checklist Keamanan Data</h3><p class="muted">Checklist ini hanya pengingat keamanan dan tidak mengubah rekomendasi penanganan.</p>
@foreach(['Cadangkan data yang masih dibutuhkan','Keluar dari akun yang masih tersambung','Lepaskan SIM/kartu memori yang ingin disimpan','Hapus data atau reset perangkat jika memungkinkan','Beri tahu mitra bila data belum dapat dihapus'] as $check)
<label class="choice"><input type="checkbox"><span>{{ $check }}</span></label>
@endforeach
</div>
@endif

<div class="cluster intake-actions">
    <button class="btn" type="submit" formaction="{{ route('user.intake.standard.pause',[$session,$item]) }}" formnovalidate>Simpan & Keluar</button>
    <button class="btn btn-primary" type="submit">{{ $position < $total ? 'Simpan & Lanjut Barang Berikutnya' : 'Selesai & Tinjau Rekomendasi' }}</button>
</div>
</form>
@endsection

@section('modals')
<div class="modal-backdrop question-help-modal" data-question-help-modal aria-hidden="true"><div class="modal question-help-dialog" role="dialog" aria-modal="true"><div class="asset-modal-head"><div><span class="eyebrow">Bantuan Pertanyaan</span><h3 data-question-help-title>Pertanyaan cek kondisi</h3></div><button class="icon-button" type="button" data-question-help-close>×</button></div><div class="hint-box question-help-modal-copy" data-question-help-copy></div><div class="asset-modal-actions"><button class="btn btn-primary" type="button" data-question-help-close>Paham</button></div></div></div>
@endsection
