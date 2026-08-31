@extends('layouts.app')

@section('title', 'Cek Kondisi · SIRKEL')
@section('topbar', 'Cek Kondisi')

@section('content')
    @php
        $questions = ($template?->questions ?? collect())->sortBy(fn($question) => $question->code === 'notes' ? 9999 : $question->sort_order)->values();
        $dataBearing = $questions->contains(fn($question) => $question->code === 'personal_data');
        $notesQuestion = $questions->firstWhere('code', 'notes');
    @endphp

    <div class="page-head">
        <div>
            <h2>Cek Kondisi Barang</h2>
            <p>{{ $asset->custom_item_name ?: $asset->category->name }} · {{ $asset->passport_code }}</p>
        </div>
    </div>

    <div class="stepper">
        <div class="step active"><span class="dot">✓</span>Barang</div>
        <div class="step active"><span class="dot">2</span>Kondisi</div>
        <div class="step"><span class="dot">3</span>Rekomendasi</div>
        <div class="step"><span class="dot">4</span>Penyerahan</div>
        <div class="step"><span class="dot">5</span>Mitra</div>
    </div>

    <form class="stack mt-16" method="post" action="{{ route('user.assets.assessment.store', $asset) }}"
        data-citizen-assessment-form data-ai-description-url="{{ route('user.assets.ai-condition-description', $asset) }}"
        data-ai-description-quota="{{ $aiDescriptionQuota['remaining'] }}"
        data-ai-topup-url="{{ route('user.ai-quota.index') }}">
        @csrf

        <div class="card">
            <h3>Pertanyaan singkat</h3>
            <p class="muted">Jawab sesuai kondisi yang benar-benar Anda ketahui. Tekan Bantuan jika sebuah pertanyaan kurang
                jelas.</p>

            <div class="stack">
                @forelse ($questions as $q)
                    <div class="field assessment-question" data-assessment-question data-question-code="{{ $q->code }}"
                        data-question-text="{{ $q->text }}" data-question-type="{{ $q->type }}"
                        data-question-required="{{ $q->required ? '1' : '0' }}" data-help-text="{{ $q->help_text }}"
                        data-validation-field="answers.{{ $q->code }}">
                        <label>
                            {{ $q->text }}
                            @if ($q->required) * @endif
                        </label>

                        @if ($q->type === 'text')
                            <textarea class="textarea" name="answers[{{ $q->code }}]" @required($q->required)
                                @if($q->code === 'notes') data-condition-notes @endif>{{ old('answers.' . $q->code) }}</textarea>

                            @if($q->code === 'notes')
                                <div class="condition-ai-assist">
                                    <div>
                                        <strong>Bantu susun catatan dari jawaban</strong>
                                        <small>Bantu rangkum kondisi penting agar lebih mudah dipahami mitra.</small>
                                        <small>Kuota tersedia: <strong
                                                data-condition-ai-quota-label>{{ number_format($aiDescriptionQuota['remaining']) }}×</strong>
                                            · <a href="{{ route('user.ai-quota.index') }}">Lihat Kuota / Top Up</a></small>
                                    </div>
                                    <button type="button" class="btn btn-sm" data-generate-condition-description disabled>
                                        <x-icon name="sparkles" size="15" /> Buat deskripsi dengan AI
                                    </button>
                                </div>
                                <div class="form-message" data-condition-ai-status>Lengkapi semua pertanyaan wajib untuk mengaktifkan
                                    bantuan AI.</div>
                            @endif
                        @else
                            <div class="choice-list">
                                @foreach ($q->options as $o)
                                    @php
                                        $inputType = $q->type === 'multi' ? 'checkbox' : 'radio';
                                        $inputName = 'answers[' . $q->code . ']' . ($q->type === 'multi' ? '[]' : '');
                                        $oldValue = old('answers.' . $q->code);
                                        $isChecked = $q->type === 'multi'
                                            ? in_array($o->value, (array) $oldValue, true)
                                            : (string) $oldValue === (string) $o->value;
                                    @endphp
                                    <label class="choice">
                                        <input type="{{ $inputType }}" name="{{ $inputName }}" value="{{ $o->value }}"
                                            @checked($isChecked) @required($q->required && $q->type !== 'multi')>
                                        <span>{{ $o->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        <div class="question-help-actions">
                            <button type="button" class="btn btn-sm question-help-btn" data-open-question-help>
                                Bantuan pertanyaan
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="alert warning">
                        Pertanyaan untuk kategori ini belum tersedia. Anda tetap dapat melanjutkan dan mitra akan memeriksa
                        barang.
                    </div>
                    <input type="hidden" name="answers[power_status]" value="unknown">
                    <input type="hidden" name="answers[damage_level]" value="unknown">
                @endforelse
            </div>
        </div>

        @if ($dataBearing)
            <div class="card">
                <h3>Checklist Keamanan Data</h3>
                <p class="muted">Lakukan sebelum perangkat diserahkan jika memungkinkan.</p>

                @foreach ([
                        'Cadangkan data yang masih dibutuhkan',
                        'Keluar dari akun yang masih tersambung jika ada',
                        'Lepaskan SIM, kartu memori, atau media penyimpanan yang ingin disimpan',
                        'Hapus data, format media, atau reset perangkat jika memungkinkan',
                        'Beri tahu mitra jika data belum dapat dihapus',
                    ] as $item)
                    <label class="choice">
                        <input type="checkbox">
                        <span>{{ $item }}</span>
                    </label>
                @endforeach

                <div class="hint-box mt-16">
                    Jika perangkat tidak dapat di-reset, beri tahu mitra bahwa perangkat masih menyimpan data dan perlu
                    ditangani dengan hati-hati.
                </div>
            </div>
        @endif

        <button class="btn btn-primary">Lihat Rekomendasi</button>
    </form>
@endsection

@section('modals')
    <div class="modal-backdrop question-help-modal" data-question-help-modal aria-hidden="true">
        <div class="modal question-help-dialog" role="dialog" aria-modal="true" aria-labelledby="question-help-modal-title">
            <div class="asset-modal-head">
                <div>
                    <span class="eyebrow">Bantuan Pertanyaan</span>
                    <h3 id="question-help-modal-title" data-question-help-title>Pertanyaan cek kondisi</h3>
                </div>
                <button class="icon-button" type="button" data-question-help-close aria-label="Tutup">×</button>
            </div>
            <div class="hint-box question-help-modal-copy" data-question-help-copy></div>
            <div class="asset-modal-actions">
                <button class="btn btn-primary" type="button" data-question-help-close>Paham</button>
            </div>
        </div>
    </div>
@endsection