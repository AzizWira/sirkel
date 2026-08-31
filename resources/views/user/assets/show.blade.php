@extends('layouts.app')

@section('title', $asset->passport_code . ' · SIRKEL')
@section('topbar', 'Detail Barang')

@section('content')
@php
    $activeRequest = $asset->activeRequest;
    $request = $activeRequest ?: $asset->latestRequest;
    $requestIsActive = $activeRequest && $request && $activeRequest->id === $request->id;
    $offer = $request?->currentOffer ?: $request?->offers?->sortByDesc('version')->first();
    $assetName = $asset->custom_item_name ?: $asset->category->name;
    $statusLabel = \App\Support\SirkelUi::assetProgress($asset->status ?? 'registered', $asset->final_path);
    $preliminaryLabel = $asset->preliminary_path
        ? \App\Support\SirkelUi::label($asset->preliminary_path)
        : 'Belum dinilai';
    $outcomeLabel = $asset->final_path
        ? \App\Support\SirkelUi::label($asset->final_path)
        : match ($asset->status) {
            'received' => 'Menunggu hasil pemeriksaan mitra',
            'in_processing' => 'Sedang ditangani oleh mitra',
            'needs_transfer' => 'Menunggu pemilihan mitra lanjutan',
            'transfer_pending' => 'Menunggu konfirmasi mitra tujuan',
            'transferred' => 'Sedang dialihkan ke mitra lanjutan',
            default => 'Belum ada hasil akhir',
        };
    $userAssessment = $asset->assessments->where('assessment_type', 'user')->sortByDesc('id')->first();
    $partnerAssessments = $asset->assessments->where('assessment_type', 'partner')->sortBy('id')->values();
    $partnerAssessment = $partnerAssessments->last();
    $currentCustody = $asset->custody->whereNull('released_at')->sortByDesc('received_at')->first();
    $pendingTransfer = $asset->transfers->where('status', 'pending')->sortByDesc('id')->first();
    $transferStepLabel = \App\Support\SirkelUi::isTransferDecision($partnerAssessment?->result_path)
        ? \App\Support\SirkelUi::label($partnerAssessment->result_path)
        : 'Layanan lanjutan';

    $answerLabel = function (string $code, mixed $value, $map = null) use ($questionMap): string {
        $sourceMap = $map ?: $questionMap;
        $question = $sourceMap->get($code);
        if (is_array($value)) {
            return collect($value)->map(function ($item) use ($question) {
                $option = $question?->options?->firstWhere('value', (string) $item);
                return $option?->label ?: \App\Support\SirkelUi::label($item);
            })->implode(', ');
        }

        $option = $question?->options?->firstWhere('value', (string) $value);
        return $option?->label ?: \App\Support\SirkelUi::label($value);
    };

    $questionLabel = function (string $code, $map = null) use ($questionMap): string {
        $sourceMap = $map ?: $questionMap;
        return $sourceMap->get($code)?->text ?: match ($code) {
            'power_status' => 'Kondisi daya / fungsi utama',
            'damage_level' => 'Tingkat kerusakan',
            'repair_feasible' => 'Kemungkinan diperbaiki menurut mitra',
            default => \Illuminate\Support\Str::of($code)->replace('_', ' ')->title()->toString(),
        };
    };
@endphp

<div class="page-head">
    <div>
        <div class="cluster">
            <span class="badge">{{ $asset->passport_code }}</span>
            <span class="badge">{{ $statusLabel }}</span>
            @if($asset->core_locked_at)
                <span class="badge warning">Data inti terkunci</span>
            @endif
        </div>

        <h2 style="margin-top:8px">{{ $assetName }}</h2>
        <p>
            {{ $asset->category->name }}
            @if($asset->brand || $asset->model_name)
                · {{ trim(($asset->brand ?? '') . ' ' . ($asset->model_name ?? '')) }}
            @endif
        </p>
    </div>

    <div class="cluster">
        <a class="btn" target="_blank" href="{{ route('passport.show', $asset->passport_code) }}">
            Lihat Paspor QR
        </a>

        @if(!$asset->preliminary_path)
            <a class="btn btn-primary" href="{{ route('user.assets.assessment', $asset) }}">
                Cek Kondisi
            </a>
        @elseif(!$activeRequest && !$asset->final_path && !$asset->core_locked_at && $asset->status !== 'offer_rejected')
            <a class="btn btn-primary" href="{{ route('user.handovers.match.form', $asset) }}">
                Pilih Mitra
            </a>
        @endif
    </div>
</div>

<div class="grid-3">
    <div class="card">
        <div class="metric-label">Rekomendasi awal</div>
        <strong>{{ $preliminaryLabel }}</strong>
    </div>

    <div class="card">
        <div class="metric-label">Berat terverifikasi</div>
        <strong>
            {{ $asset->verified_weight_kg !== null
    ? number_format((float) $asset->verified_weight_kg, 3, ',', '.') . ' kg'
    : 'Belum ada' }}
        </strong>
    </div>

    <div class="card">
        <div class="metric-label">Hasil akhir</div>
        <strong>{{ $outcomeLabel }}</strong>
    </div>
</div>

@if($asset->final_path)
    @php
        $verifiedCircular = \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path);
        $returnedToOwner = $asset->final_path === 'RETURNED_TO_OWNER';
        $splitParent = $asset->final_path === 'SPLIT_TO_SUB_BATCHES';
    @endphp
    <div class="next-action-card {{ $verifiedCircular ? 'success-state' : 'warning-state' }} mt-16">
        <div class="next-action-kicker">Status saat ini</div>
        @if($verifiedCircular)
            <h3>Penanganan sirkular sudah selesai</h3>
            <p>Hasil <strong>{{ \App\Support\SirkelUi::label($asset->final_path) }}</strong> sudah dikonfirmasi. Riwayat lengkap
                tersedia di Paspor SIRKEL.</p>
        @elseif($returnedToOwner)
            <h3>Barang dikembalikan ke pemilik</h3>
            <p>Barang sudah kembali kepada Anda. Penanganan ditutup dan tidak masuk perhitungan dampak.</p>
        @elseif($splitParent)
            <h3>Kelompok barang sudah dipisahkan</h3>
            <p>Penanganan berlanjut pada kelompok hasil dengan paspor masing-masing.</p>
        @else
            <h3>Riwayat ditutup tanpa hasil akhir terverifikasi</h3>
            <p>Riwayat ditutup tanpa hasil akhir yang dapat dikonfirmasi.</p>
        @endif
    </div>
@elseif(!$asset->preliminary_path)
    <div class="next-action-card mt-16">
        <div class="next-action-kicker">Langkah Anda berikutnya</div>
        <h3>Lakukan cek kondisi singkat</h3>
        <p>Jawab singkat sesuai kondisi yang Anda ketahui.</p>
        <a class="btn btn-primary" href="{{ route('user.assets.assessment', $asset) }}">Cek Kondisi</a>
    </div>
@elseif($matchingHelpIssue && in_array($matchingHelpIssue->status, ['open', 'in_review'], true) && !$asset->core_locked_at)
    <div class="next-action-card mt-16">
        <div class="next-action-kicker">Bantuan pencarian mitra</div>
        @if($matchingHelpIssue->status === 'in_review' && $matchingHelpIssue->request && $matchingHelpIssue->request->status === 'pending')
            <h3>SIRKEL sedang menghubungi mitra</h3>
            <p>Permintaan sudah diteruskan ke
                <strong>{{ $matchingHelpIssue->request->partner->business_name ?? 'calon mitra' }}</strong>. Anda akan mendapat
                pemberitahuan setelah mitra merespons.</p>
        @else
            <h3>SIRKEL sedang mencarikan mitra</h3>
            <p>Permintaan bantuan Anda sedang ditangani. Anda tetap dapat mengubah cara penyerahan jika diperlukan.</p>
        @endif
        @if($activeRequest)
            <a class="btn" href="#penyerahan-aktif">Lihat Penyerahan</a>
        @else
            <a class="btn" href="{{ route('user.handovers.match.form', $asset) }}">Ubah Cara Penyerahan</a>
        @endif
    </div>
@elseif($request && $request->status === 'offer_rejected' && !$asset->core_locked_at)
    <div class="next-action-card warning-state mt-16">
        <div class="next-action-kicker">Penawaran ditolak</div>
        <h3>Pilih langkah negosiasi berikutnya</h3>
        <p>Data cara penyerahan, lokasi, dan jadwal sebelumnya masih tersimpan. Anda tidak perlu mengisi ulang jika ingin
            meminta harga baru atau mengganti mitra.</p>
        <a class="btn" href="#tindak-lanjut-penawaran">Pilih Tindak Lanjut</a>
    </div>
@elseif(!$activeRequest && !$asset->core_locked_at)
    <div class="next-action-card mt-16">
        <div class="next-action-kicker">Langkah Anda berikutnya</div>
        <h3>Pilih cara penyerahan dan mitra</h3>
        <p>Pilih dijemput atau antar sendiri, lalu tentukan mitra.</p>
        <a class="btn btn-primary" href="{{ route('user.handovers.match.form', $asset) }}">Pilih Mitra</a>
    </div>
@elseif($asset->status === 'awaiting_donation_proof')
<div class="next-action-card warning-state mt-16">
    <div class="next-action-kicker">Donasi masih berjalan</div>
    <h3>Menunggu mitra menyalurkan barang</h3>
    <p>Barang sudah dinyatakan layak disalurkan, tetapi belum dianggap selesai. Mitra masih bertanggung jawab sampai
        foto, waktu, dan lokasi Bukti Donasi dicatat.</p>
    @if($currentCustody?->partner)
    <div class="cluster">
        <strong>{{ $currentCustody->partner->business_name }}</strong>@php($donationWa = \App\Support\SirkelUi::whatsappUrl($currentCustody->partner->phone, 'Halo ' . $currentCustody->partner->business_name . ', saya ingin menanyakan progres donasi ' . $asset->passport_code . ' di SIRKEL.'))@if($donationWa)<a
                    class="btn btn-sm" href="{{ $donationWa }}" target="_blank" rel="noopener">Hubungi Mitra ↗</a>@endif</div>
                @endif
            </div>
        @elseif(in_array($asset->status, ['received', 'in_processing'], true))
    <div class="next-action-card mt-16">
        <div class="next-action-kicker">Tidak ada tindakan yang perlu Anda lakukan</div>
        <h3>{{ $asset->status === 'in_processing' ? 'Barang sedang ditangani oleh mitra' : 'Barang sedang diperiksa oleh mitra' }}
        </h3>
        <p>{{ $asset->status === 'in_processing' ? 'Penanganan masih berlangsung. Hasil akhir akan muncul setelah selesai.' : 'Barang sudah diterima dan sedang diperiksa oleh mitra.' }}
        </p>
    </div>
@elseif(in_array($asset->status, ['needs_transfer', 'transfer_pending', 'transferred'], true))
    <div class="next-action-card warning-state mt-16">
        <div class="next-action-kicker">Penanganan masih berjalan</div>
        <h3>{{ in_array($asset->status, ['transfer_pending', 'transferred'], true) ? 'Menunggu mitra lanjutan menerima barang' : $transferStepLabel }}
        </h3>
        <p>@if($pendingTransfer) Pengalihan sedang diajukan ke
        <strong>{{ $pendingTransfer->toPartner?->business_name ?? 'mitra lanjutan' }}</strong>. @else Mitra sedang
            memilih layanan lanjutan. @endif</p>
    </div>
@elseif($request)
<div class="next-action-card mt-16">
    <div class="next-action-kicker">Permintaan penyerahan</div>
    <h3>{{ \App\Support\SirkelUi::handoverStatus($request->status) }}</h3>
    <p>Lihat detail penyerahan di bawah.</p>
</div>
@endif

<div class="card asset-detail-section">
    <div class="split">
        <div>
            <h3>Informasi Barang</h3>
        </div>
        <span class="badge">{{ \App\Support\SirkelUi::label($asset->tracking_type) }}</span>
    </div>

    <div class="detail-grid mt-16">
        <div class="detail-item">
            <span class="detail-label">Kategori</span>
            <div class="detail-value">{{ $asset->category->name }}</div>
            @if($asset->category->group)
                <div class="text-sm muted">{{ $asset->category->group->name }}</div>
            @endif
        </div>

        <div class="detail-item">
            <span class="detail-label">Nama barang</span>
            <div class="detail-value">{{ $assetName }}</div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Merek / model</span>
            <div class="detail-value">{{ trim(($asset->brand ?? '') . ' ' . ($asset->model_name ?? '')) ?: 'Tidak diisi' }}
            </div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Pencatatan</span>
            <div class="detail-value">
                {{ \App\Support\SirkelUi::label($asset->tracking_type) }}
                @if($asset->tracking_type === 'batch')
                    · {{ $asset->quantity }} unit
                @endif
            </div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Berat perkiraan warga</span>
            <div class="detail-value">
                {{ $asset->estimated_weight_kg !== null
    ? number_format((float) $asset->estimated_weight_kg, 3, ',', '.') . ' kg'
    : 'Tidak diisi' }}
            </div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Tidak digunakan sejak</span>
            <div class="detail-value">{{ $asset->dormant_since?->format('d M Y') ?? 'Tidak diisi' }}</div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Asal barang</span>
            <div class="detail-value">
                {{ collect([$asset->origin_village, $asset->origin_district, 'Surabaya'])->filter()->implode(', ') ?: 'Belum diisi' }}
            </div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Terdaftar pada</span>
            <div class="detail-value">{{ $asset->created_at?->format('d M Y H:i') }}</div>
        </div>

        @if($asset->condition_class)
            <div class="detail-item">
                <span class="detail-label">Kelas kondisi</span>
                <div class="detail-value">{{ \App\Support\SirkelUi::label($asset->condition_class) }}</div>
            </div>
        @endif

        @if($asset->handover_type)
            <div class="detail-item">
                <span class="detail-label">Cara penyerahan yang dipilih</span>
                <div class="detail-value">{{ \App\Support\SirkelUi::label($asset->handover_type) }}</div>
            </div>
        @endif
    </div>

    <div class="divider"></div>
    <span class="detail-label">Keterangan kondisi saat didaftarkan</span>
    <div>{{ $asset->description ?: 'Tidak ada keterangan tambahan.' }}</div>
</div>

@if($asset->photos->count())
    <div class="card asset-detail-section">
        <h3>Foto Barang</h3>
        <div class="asset-photos">
            @foreach($asset->photos as $photo)
                <img src="{{ asset('storage/' . $photo->path) }}" alt="Foto {{ $assetName }}">
            @endforeach
        </div>
    </div>
@endif

<div class="two-col asset-detail-section">
    <div class="card">
        <div class="split">
            <div>
                <h3>Hasil Cek Kondisi Warga</h3>
            </div>
            @if($userAssessment?->result_path)
                <span class="badge">{{ \App\Support\SirkelUi::label($userAssessment->result_path) }}</span>
            @endif
        </div>

        @if($userAssessment)
            <div class="assessment-answer-list mt-16">
                @foreach(($userAssessment->answers_json ?? []) as $code => $value)
                    <div class="assessment-answer">
                        <div class="answer-question">{{ $questionLabel((string) $code) }}</div>
                        <div class="answer-value">{{ $answerLabel((string) $code, $value) }}</div>
                    </div>
                @endforeach
            </div>

            @if($userAssessment->summary)
                <div class="hint-box mt-16">
                    <strong>Penjelasan rekomendasi</strong>
                    <div style="margin-top:4px" class="rich-ai-text" data-ai-markdown>{{ $userAssessment->summary }}</div>
                </div>
            @endif
        @else
            <div class="empty">Cek kondisi belum dilakukan.</div>
        @endif
    </div>

    <div class="card">
        <div class="split">
            <div>
                <h3>Pemeriksaan Mitra</h3>
            </div>
            @if($partnerAssessment?->result_path)
                <span
                    class="badge {{ \App\Support\SirkelUi::isTransferDecision($partnerAssessment->result_path) ? 'warning' : (\App\Support\SirkelUi::isVerifiedOutcome($partnerAssessment->result_path) ? 'success' : '') }}">{{ \App\Support\SirkelUi::label($partnerAssessment->result_path) }}</span>
            @endif
        </div>

        @if($partnerAssessments->isNotEmpty())
            <div class="stack mt-16">
                @foreach($partnerAssessments as $index => $assessment)
                    <div class="card" style="padding:14px">
                        <div class="split">
                            <div>
                                <strong>Pemeriksaan {{ $index + 1 }}</strong>
                                <div class="text-sm muted">
                                    {{ $assessment->verified_at?->format('d M Y H:i') ?? $assessment->created_at?->format('d M Y H:i') }}
                                    @if($assessment->assessor?->name)
                                        · {{ $assessment->assessor->name }}
                                    @endif
                                </div>
                            </div>
                            @if($assessment->result_path)
                                <span
                                    class="badge {{ \App\Support\SirkelUi::isTransferDecision($assessment->result_path) ? 'warning' : (\App\Support\SirkelUi::isVerifiedOutcome($assessment->result_path) ? 'success' : '') }}">{{ \App\Support\SirkelUi::label($assessment->result_path) }}</span>
                            @endif
                        </div>

                        <div class="assessment-answer-list mt-16">
                            @foreach(($assessment->answers_json ?? []) as $code => $value)
                                <div class="assessment-answer">
                                    <div class="answer-question">{{ $questionLabel((string) $code, $partnerQuestionMap) }}</div>
                                    <div class="answer-value">{{ $answerLabel((string) $code, $value, $partnerQuestionMap) }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Berat saat pemeriksaan</span>
                            <div class="detail-value">
                                {{ $assessment->verified_weight_kg !== null
                    ? number_format((float) $assessment->verified_weight_kg, 3, ',', '.') . ' kg'
                    : 'Belum dicatat' }}
                            </div>
                        </div>

                        @if($assessment->summary)
                            <div class="hint-box mt-16">
                                <strong>Ringkasan pemeriksaan</strong>
                                <div style="margin-top:4px" class="rich-ai-text" data-ai-markdown>{{ $assessment->summary }}</div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">Belum ada pemeriksaan mitra.</div>
        @endif
    </div>
</div>

@if($currentCustody)
    <div class="card asset-detail-section">
        <div class="split">
            <div>
                <h3>Barang Sedang Ditangani</h3>
            </div>
            <span class="badge success">Dalam penanganan mitra</span>
        </div>
        <div class="detail-grid mt-16">
            <div class="detail-item">
                <span class="detail-label">Mitra saat ini</span>
                <div class="detail-value">{{ $currentCustody->partner?->business_name ?? 'Mitra SIRKEL' }}</div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Diterima sejak</span>
                <div class="detail-value">{{ $currentCustody->received_at?->format('d M Y H:i') ?? '-' }}</div>
            </div>
        </div>
    </div>
@endif

@if($request)
    <div class="card asset-detail-section" id="{{ $requestIsActive ? 'penyerahan-aktif' : 'penyerahan-terakhir' }}">
        <div class="split">
            <div>
                <h3>{{ $requestIsActive ? 'Penyerahan Aktif' : 'Penyerahan Terakhir' }}</h3>
                <p class="muted mb-0">
                    {{ $request->partner->business_name }} · {{ \App\Support\SirkelUi::label($request->method) }}
                    @if($request->distance_km !== null)
                        · {{ number_format((float) $request->distance_km, 1, ',', '.') }} km
                    @endif
                </p>
            </div>

            <div class="cluster">
                <span class="badge">{{ \App\Support\SirkelUi::handoverStatus($request->status) }}</span>
                @if($request->method === 'pickup')
                    <span class="badge {{ $request->outside_radius ? 'warning' : 'success' }}">
                        {{ $request->outside_radius ? 'Di luar radius reguler' : 'Dalam radius' }}
                    </span>
                @endif
            </div>
        </div>

        <div class="detail-grid mt-16">
            <div class="detail-item">
                <span class="detail-label">Jenis penyerahan</span>
                <div class="detail-value">{{ \App\Support\SirkelUi::label($request->effectiveHandoverType()) }}</div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Cara serah terima</span>
                <div class="detail-value">{{ \App\Support\SirkelUi::label($request->method) }}</div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Rencana tanggal</span>
                <div class="detail-value">{{ $request->requested_date?->format('d M Y') ?? 'Belum ditentukan' }}</div>
                @if($request->requested_time_start || $request->requested_time_end)
                    <div class="text-sm muted">{{ $request->requested_time_start ?: '?' }} –
                        {{ $request->requested_time_end ?: '?' }}</div>
                @endif
            </div>
            <div class="detail-item">
                <span class="detail-label">Lokasi penyerahan</span>
                @if($request->method === 'pickup')
                    <div class="detail-value">{{ $request->pickup_address ?: 'Alamat penjemputan belum diisi' }}</div>
                    <div class="text-sm muted">
                        {{ collect([$request->pickup_village, $request->pickup_district])->filter()->implode(', ') }}</div>
                @else
                    <div class="detail-value">Antar langsung ke lokasi mitra</div>
                    <div class="text-sm muted">Antar langsung ke mitra</div>
                @endif
            </div>
        </div>

        @if($requestIsActive && $request->partner_proposed_time && $request->schedule_status === 'proposed')
            <div class="alert warning">
                <strong>Usulan jadwal mitra:</strong>
                {{ $request->partner_proposed_time->format('d M Y H:i') }}

                <form method="post" action="{{ route('user.handovers.schedule.accept', $request) }}" style="margin-top:8px">
                    @csrf
                    <button class="btn btn-sm btn-primary">Terima Jadwal</button>
                </form>
            </div>
        @endif

        @if($offer)
            <div class="card" style="margin-top:12px">
                <div class="split">
                    <div>
                        <div class="metric-label">Penawaran awal · versi {{ $offer->version }}</div>
                        <div class="metric">
                            {{ $offer->amount !== null
                    ? 'Rp' . number_format((float) $offer->amount, 0, ',', '.')
                    : 'Tanpa nilai uang' }}
                        </div>
                        <div class="text-sm muted">
                            Berlaku sampai {{ $offer->expires_at->format('d M Y H:i') }}
                        </div>
                    </div>

                    <span class="badge">{{ \App\Support\SirkelUi::label($offer->status) }}</span>
                </div>

                @if($offer->note)
                    <p>{{ $offer->note }}</p>
                @endif

                @if($requestIsActive && $offer->status === 'waiting_user' && $offer->expires_at->isFuture())
                    <div class="cluster">
                        <form method="post" action="{{ route('user.offers.respond', $offer) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accept">
                            <button class="btn btn-primary">Terima Penawaran</button>
                        </form>

                        <details>
                            <summary class="btn">Tolak</summary>
                            <form class="stack card" style="margin-top:8px" method="post"
                                action="{{ route('user.offers.respond', $offer) }}">
                                @csrf
                                <input type="hidden" name="decision" value="reject">

                                <select class="select" name="rejection_reason" required>
                                    <option value="">Pilih alasan</option>
                                    <option value="Penawaran terlalu rendah">Penawaran terlalu rendah</option>
                                    <option value="Jadwal penjemputan tidak cocok">Jadwal penjemputan tidak cocok</option>
                                    <option value="Lokasi/penyerahan kurang sesuai">Lokasi/penyerahan kurang sesuai</option>
                                    <option value="Memilih mitra lain">Memilih mitra lain</option>
                                    <option value="Tidak jadi menyerahkan barang">Tidak jadi menyerahkan barang</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>

                                <textarea class="textarea" name="rejection_note" placeholder="Catatan tambahan"></textarea>
                                <button class="btn btn-danger">Tolak Penawaran</button>
                            </form>
                        </details>
                    </div>
                @endif
            </div>
        @endif

        @if($request->status === 'offer_rejected' && !$asset->core_locked_at)
            <div class="card stack" id="tindak-lanjut-penawaran" style="margin-top:12px">
                <div><span class="eyebrow">Setelah menolak penawaran</span>
                    <h3>Apa yang ingin Anda lakukan?</h3>
                    <p class="muted mb-0">Menolak harga tidak otomatis berarti menolak mitranya.</p>
                </div>
                <div class="review-grid">
                    <form class="card stack" method="post" action="{{ route('user.handovers.offer-rejected.next', $request) }}">
                        @csrf<input type="hidden" name="action" value="reoffer"><strong>Minta Penawaran Baru</strong>
                        <p class="text-sm muted mb-0">Tetap dengan {{ $request->partner->business_name }}. Mitra dapat mengirim
                            versi harga berikutnya tanpa mengubah data penyerahan.</p><button class="btn btn-primary">Minta
                            Harga Baru</button>
                    </form>
                    <form class="card stack" method="post" action="{{ route('user.handovers.offer-rejected.next', $request) }}">
                        @csrf<input type="hidden" name="action" value="change_partner"><strong>Ganti Mitra</strong>
                        <p class="text-sm muted mb-0">Kembali ke daftar mitra dengan cara penyerahan, lokasi, dan jadwal
                            sebelumnya tetap tersimpan.</p><button class="btn">Pilih Mitra Lain</button>
                    </form>
                    <form class="card stack" method="post" action="{{ route('user.handovers.offer-rejected.next', $request) }}">
                        @csrf<input type="hidden" name="action" value="cancel"><strong>Batalkan Penyerahan</strong>
                        <p class="text-sm muted mb-0">Tutup permintaan ini. Riwayat penawaran tetap tersimpan sebagai audit.</p>
                        <button class="btn btn-danger" data-confirm="Batalkan penyerahan ini?">Batalkan</button>
                    </form>
                </div>
            </div>
        @endif

        @if($offer && $offer->final_agreed_value !== null)
            <div class="card" style="margin-top:12px">
                <div class="split">
                    <div>
                        <div class="metric-label">Nilai akhir setelah pemeriksaan</div>
                        <div class="metric">Rp{{ number_format((float) $offer->final_agreed_value, 0, ',', '.') }}</div>
                        @if($offer->final_value_reason)
                            <p class="text-sm muted">{{ $offer->final_value_reason }}</p>
                        @endif
                    </div>

                    @if($offer->final_confirmed_at)
                        <span class="badge success">Sudah dikonfirmasi</span>
                    @else
                        <span class="badge warning">Menunggu konfirmasi</span>
                    @endif
                </div>

                @if(!$offer->final_confirmed_at)
                    <div class="cluster">
                        <form method="post" action="{{ route('user.offers.final', $offer) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accept">
                            <button class="btn btn-primary">Setujui Nilai Akhir</button>
                        </form>

                        <details>
                            <summary class="btn">Ajukan Keberatan</summary>
                            <form class="stack card" style="margin-top:8px" method="post"
                                action="{{ route('user.offers.final', $offer) }}">
                                @csrf
                                <input type="hidden" name="decision" value="reject">
                                <textarea class="textarea" name="reason" required
                                    placeholder="Jelaskan alasan keberatan"></textarea>
                                <button class="btn btn-danger">Kirim Keberatan</button>
                            </form>
                        </details>
                    </div>
                @endif

                <p class="text-sm muted">
                    Pembayaran dilakukan langsung dengan mitra.
                </p>
            </div>
        @endif

        @if($requestIsActive && !$asset->core_locked_at && !in_array($request->status, ['declined', 'offer_rejected'], true))
            <details style="margin-top:12px">
                <summary class="btn">Batalkan permintaan</summary>
                <form class="card stack" method="post" action="{{ route('user.handovers.cancel', $request) }}"
                    style="margin-top:8px">
                    @csrf
                    <textarea class="textarea" name="reason" required placeholder="Alasan pembatalan"></textarea>
                    <button class="btn btn-danger">Batalkan Permintaan</button>
                </form>
            </details>
        @endif
    </div>
@endif

@if($asset->donationProof)
@php($proof = $asset->donationProof)
<div class="card asset-detail-section">
    <div class="split">
        <div><span class="eyebrow">Bukti Donasi</span>
            <h3>Penyaluran tercatat</h3>
        </div><span class="badge success">Donasi selesai</span>
    </div>
    <div class="two-col mt-16">
        <div><img src="{{ asset('storage/' . $proof->photo_path) }}"
                alt="Bukti penyaluran donasi {{ $asset->passport_code }}"
                style="width:100%;max-height:360px;object-fit:cover;border-radius:12px"></div>
        <div class="stack">
            <div class="detail-item"><span class="detail-label">Disalurkan oleh</span>
                <div class="detail-value">{{ $proof->partner?->business_name ?? 'Mitra SIRKEL' }}</div>
            </div>
            <div class="detail-item"><span class="detail-label">Penerima</span>
                <div class="detail-value">
                    {{ $proof->recipient_type === 'individual' ? 'Individu (identitas disembunyikan)' : ($proof->recipient_name ?: \App\Support\SirkelUi::label($proof->recipient_type)) }}
                </div>
            </div>
            <div class="detail-item"><span class="detail-label">Waktu penyaluran</span>
                <div class="detail-value">{{ $proof->donated_at?->format('d M Y H:i') }}</div>
            </div>
            <div class="detail-item"><span class="detail-label">Lokasi bukti</span>
                <div class="detail-value">{{ $proof->location_label ?: 'Lokasi perangkat mitra tercatat' }}</div>
                <div class="text-sm muted">
                    {{ $proof->location_accuracy_m ? 'Akurasi perangkat ±' . number_format($proof->location_accuracy_m, 0, ',', '.') . ' m' : 'Koordinat presisi tidak ditampilkan untuk menjaga privasi penerima.' }}
                </div>
            </div>
            @if($proof->recipient_note)
            <div class="hint-box">{{ $proof->recipient_note }}</div>@endif
        </div>
    </div>
</div>
@endif

<div class="card asset-detail-section">
    <div class="split">
        <h3>Riwayat Perjalanan Barang</h3>
        <a class="btn btn-sm" target="_blank" href="{{ route('passport.show', $asset->passport_code) }}">
            Buka Paspor Publik
        </a>
    </div>

    <div class="timeline" style="margin-top:18px">
        @forelse($asset->events as $event)
            <div class="timeline-item">
                <span class="timeline-dot"></span>
                <div>
                    <strong>{{ $event->title }}</strong>
                    <div class="text-sm muted">{{ $event->occurred_at->format('d M Y H:i') }}</div>
                    <p class="text-sm">{{ $event->description }}</p>
                </div>
            </div>
        @empty
            <p class="muted">Belum ada riwayat untuk barang ini.</p>
        @endforelse
    </div>
</div>

<div class="card asset-detail-section">
    <h3>Laporkan Masalah</h3>
    <details>
        <summary class="btn btn-sm">Buat laporan</summary>
        <form class="stack" method="post" action="{{ route('user.issues.store') }}" style="margin-top:12px">
            @csrf
            <input type="hidden" name="asset_id" value="{{ $asset->id }}">

            @if($request)
                <input type="hidden" name="handover_request_id" value="{{ $request->id }}">
            @endif

            <select class="select" name="category" required>
                <option value="partner_no_show">Mitra tidak datang</option>
                <option value="item_mismatch">Kondisi/barang tidak sesuai</option>
                <option value="value_problem">Masalah nilai akhir</option>
                <option value="behavior">Perilaku tidak sesuai</option>
                <option value="no_update">Tidak ada pembaruan</option>
                <option value="other">Lainnya</option>
            </select>

            <textarea class="textarea" name="description" required
                placeholder="Jelaskan masalah yang terjadi"></textarea>
            <button class="btn btn-primary">Kirim Laporan</button>
        </form>
    </details>
</div>
@endsection