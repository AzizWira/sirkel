@extends('layouts.app')
@section('title','Pertanyaan Bulk AI · SIRKEL')
@section('topbar','Cek Kondisi Banyak Barang')
@section('content')
@php $answers=old('answers',$answers); $itemMap=$items->keyBy(fn($i)=>(string)$i->asset->public_id); $total=count($questions); @endphp
<div class="page-head"><div><span class="eyebrow">Bulk AI · PRO</span><h2>Pertanyaan kondisi untuk semua barang</h2><p>SIRKEL menyiapkan {{ $total }} pertanyaan yang relevan dari kelompok barang yang sudah Anda tinjau. Jawab hanya berdasarkan kondisi yang benar-benar Anda ketahui.</p></div><button class="btn" type="submit" form="bulk-questionnaire-form" formaction="{{ route('user.bulk.answers.pause',$session) }}" formnovalidate>Simpan & Keluar</button></div>
<div class="bulk-question-summary mb-16">@foreach($items as $item)<span>{{ $item->asset->custom_item_name ?: $item->asset->category?->name }}{{ $item->asset->quantity>1?' ×'.$item->asset->quantity:'' }}</span>@endforeach</div>

<form id="bulk-questionnaire-form" class="stack" method="post" action="{{ route('user.bulk.answers.complete',$session) }}" data-bulk-questionnaire-form data-autosave-url="{{ route('user.bulk.answers.autosave',$session) }}">
@csrf
<div class="card bulk-question-shell">
    <div class="split bulk-question-progress"><div><strong data-bulk-question-progress>1 dari {{ $total }}</strong><small class="muted" data-bulk-autosave-state>Jawaban akan disimpan otomatis</small></div><div class="progress-track"><span data-bulk-progress-bar style="width:{{ $total?100/$total:100 }}%"></span></div></div>

    @foreach($questions as $question)
    @php $qid=$question['id']; $saved=$answers[$qid]??null; @endphp
    <section class="bulk-question" data-bulk-question data-question-index="{{ $loop->index }}" data-question-required="{{ ($question['required']??true)?'1':'0' }}" data-question-type="{{ $question['type'] }}" @if(!$loop->first) hidden @endif>
        <div class="bulk-question-number">Pertanyaan {{ $loop->iteration }}</div>
        <h3>{{ $question['text'] }} @if($question['required']??true)*@endif</h3>
        @if(count($question['targets']??[]) < $items->count())
            <div class="bulk-targets">Untuk: @foreach($question['targets'] as $target)<span>{{ $itemMap[$target]?->asset?->custom_item_name ?: $itemMap[$target]?->asset?->category?->name }}</span>@endforeach</div>
        @endif

        @if($question['type']==='matrix_single')
            <div class="bulk-matrix">
            @foreach($question['targets'] as $target)
                @php $targetItem=$itemMap[$target]??null; $targetSaved=is_array($saved)?($saved[$target]??null):null; @endphp
                @if($targetItem)<div class="bulk-matrix-row"><strong>{{ $targetItem->asset->custom_item_name ?: $targetItem->asset->category?->name }}{{ $targetItem->asset->quantity>1?' ×'.$targetItem->asset->quantity:'' }}</strong><div class="choice-list compact">@foreach($question['options'] as $option)<label class="choice"><input type="radio" name="answers[{{ $qid }}][{{ $target }}]" value="{{ $option['value'] }}" @checked((string)$targetSaved===(string)$option['value'])><span>{{ $option['label'] }}</span></label>@endforeach</div></div>@endif
            @endforeach
            </div>
        @elseif($question['type']==='matrix_multi')
            <div class="bulk-matrix">
            @foreach($question['targets'] as $target)
                @php $targetItem=$itemMap[$target]??null; $targetSaved=is_array($saved)?(array)($saved[$target]??[]):[]; @endphp
                @if($targetItem)<div class="bulk-matrix-row"><strong>{{ $targetItem->asset->custom_item_name ?: $targetItem->asset->category?->name }}{{ $targetItem->asset->quantity>1?' ×'.$targetItem->asset->quantity:'' }}</strong><div class="choice-list compact">@foreach($question['options'] as $option)<label class="choice"><input type="checkbox" name="answers[{{ $qid }}][{{ $target }}][]" value="{{ $option['value'] }}" @checked(in_array((string)$option['value'],array_map('strval',$targetSaved),true))><span>{{ $option['label'] }}</span></label>@endforeach</div></div>@endif
            @endforeach
            </div>
        @elseif($question['type']==='multi')
            <div class="choice-list">@foreach($question['options'] as $option)<label class="choice"><input type="checkbox" name="answers[{{ $qid }}][]" value="{{ $option['value'] }}" @checked(in_array((string)$option['value'],array_map('strval',(array)$saved),true))><span>{{ $option['label'] }}</span></label>@endforeach</div>
        @elseif($question['type']==='text')
            <textarea class="textarea" name="answers[{{ $qid }}]" maxlength="1200">{{ is_scalar($saved)?$saved:'' }}</textarea>
        @else
            <div class="choice-list">@foreach($question['options'] as $option)<label class="choice"><input type="radio" name="answers[{{ $qid }}]" value="{{ $option['value'] }}" @checked((string)$saved===(string)$option['value'])><span>{{ $option['label'] }}</span></label>@endforeach</div>
        @endif
    </section>
    @endforeach

    <div class="bulk-question-nav">
        <button class="btn" type="button" data-bulk-prev disabled>Sebelumnya</button>
        <button class="btn btn-primary" type="button" data-bulk-next @if($total<=1) hidden @endif>Berikutnya</button>
        <button class="btn btn-primary" type="submit" data-bulk-finish @if($total>1) hidden @endif>Selesai & Tinjau Rekomendasi</button>
    </div>
</div>
<button class="btn" type="submit" formaction="{{ route('user.bulk.answers.pause',$session) }}" formnovalidate>Simpan & Keluar</button>
</form>
@endsection
