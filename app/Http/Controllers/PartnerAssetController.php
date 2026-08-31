<?php

namespace App\Http\Controllers;

use App\Models\{Asset, AssetAssessment, HandoverRequest, PartnerTransfer, QuestionnaireTemplate};
use App\Services\{AssetEventService, AssetFlowService, NotificationService, QuestionnaireService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerAssetController extends Controller
{
    public function index(Request $request)
    {
        $profile = $this->profile();
        $scope = $request->query('scope', 'active');
        if (!in_array($scope, ['active', 'history'], true)) {
            $scope = 'active';
        }

        $query = Asset::with(['category', 'owner', 'custody.partner', 'assessments', 'transfers.toPartner'])
            ->whereHas('custody', function ($q) use ($profile, $scope) {
                $q->where('partner_profile_id', $profile->id);
                if ($scope === 'active') {
                    $q->whereNull('released_at');
                } else {
                    $q->whereNotNull('released_at');
                }
            });

        if ($scope === 'active') {
            $query->whereNull('final_path');
        }

        return view('partner.assets.index', [
            'assets' => $query->latest('updated_at')->paginate(15)->withQueryString(),
            'scope' => $scope,
            'profile' => $profile,
        ]);
    }

    public function show(Asset $asset)
    {
        $profile = $this->profile();
        $this->authorizeHandledAsset($asset, $profile->id);

        $asset->load([
            'category.group',
            'owner',
            'photos',
            'assessments.assessor',
            'events',
            'custody.partner',
            'requests.partner',
            'requests.offers',
            'transfers.fromPartner',
            'transfers.toPartner',
            'donationProof',
        ]);
        $profile->load('capabilities');

        $handover = $asset->requests
            ->where('partner_profile_id', $profile->id)
            ->sortByDesc('id')
            ->first();

        $currentCustody = $asset->custody
            ->where('partner_profile_id', $profile->id)
            ->whereNull('released_at')
            ->sortByDesc('received_at')
            ->first();

        // Yang menentukan langkah mitra saat ini adalah pemeriksaan oleh mitra saat ini,
        // bukan hasil transfer dari mitra sebelumnya.
        $lastAssessment = $asset->assessments
            ->where('assessment_type', 'partner')
            ->where('assessor_user_id', $profile->user_id)
            ->sortByDesc('id')
            ->first();

        $pendingOutgoingTransfer = $asset->transfers
            ->where('from_partner_id', $profile->id)
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();

        $pendingIncomingTransfer = $asset->transfers
            ->where('to_partner_id', $profile->id)
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();

        $activeCustody = $asset->custody
            ->whereNull('released_at')
            ->sortByDesc('received_at')
            ->first();

        $flow = app(AssetFlowService::class);
        $partnerTemplate = $this->partnerTemplateForAsset($asset);

        return view('partner.assets.show', [
            'asset' => $asset,
            'profile' => $profile,
            'handover' => $handover,
            'currentCustody' => $currentCustody,
            'lastAssessment' => $lastAssessment,
            'pendingOutgoingTransfer' => $pendingOutgoingTransfer,
            'pendingIncomingTransfer' => $pendingIncomingTransfer,
            'activeCustody' => $activeCustody,
            'partnerTemplate' => $partnerTemplate,
            'continuationOption' => $flow->continuationOption($profile),
            'completionOptions' => $flow->completionOptions($asset, $profile),
            'transferOptions' => $flow->transferOptions($asset, $profile),
            'handoverGoalTitle' => $flow->handoverGoalTitle($asset),
            'handoverGoalHelp' => $flow->handoverGoalHelp($asset),
        ]);
    }

    public function assess(Request $request, Asset $asset)
    {
        $profile = $this->profile();
        $asset->loadMissing('category.group');
        $template = $this->partnerTemplateForAsset($asset);
        $this->normalizeLegacyPartnerAnswers($request, $template);

        $data = $request->validate([
            'handling_decision' => 'required|string|max:80',
            'summary' => 'nullable|string|max:1200',
            'final_agreed_value' => 'nullable|numeric|min:0|max:999999999',
            'final_value_reason' => 'nullable|string|max:500',
        ]);
        $answers = $this->validatePartnerAnswers($request, $template);
        $answers += [
            'power_status' => 'unknown',
            'damage_level' => 'unknown',
            'repair_feasible' => 'unknown',
            'hazard_found' => 'unknown',
            'recovery_potential' => 'unknown',
        ];

        $decision = $data['handling_decision'];
        $summary = filled($data['summary'] ?? null) ? trim((string) $data['summary']) : null;

        $flow = app(AssetFlowService::class);
        $isTransfer = false;
        $isContinue = false;
        $isDonationPending = false;

        DB::transaction(function () use ($request, $asset, $profile, $data, $answers, $decision, $summary, $flow, &$isTransfer, &$isContinue, &$isDonationPending) {
            $locked = Asset::with('category')->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (!$locked->core_locked_at) {
                throw ValidationException::withMessages(['handling_decision' => 'Barang belum tercatat diterima secara fisik. Muat ulang halaman dan selesaikan proses penerimaan terlebih dahulu.']);
            }
            abort_unless(
                $locked->custody()->where('partner_profile_id', $profile->id)->whereNull('released_at')->exists(),
                403,
                'Barang ini tidak sedang berada dalam tanggung jawab mitra Anda.'
            );
            if ($locked->final_path) {
                throw ValidationException::withMessages(['handling_decision' => 'Barang ini sudah memiliki hasil akhir. Muat ulang halaman untuk melihat status terbaru.']);
            }
            if (!in_array($locked->status, ['received', 'in_processing'], true)) {
                throw ValidationException::withMessages(['handling_decision' => 'Pemeriksaan pada tahap ini sudah disimpan atau status barang sudah berubah. Muat ulang halaman dan lanjutkan langkah yang ditampilkan.']);
            }
            if (PartnerTransfer::where('asset_id', $locked->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['handling_decision' => 'Pengalihan ke mitra lain sedang menunggu respons. Selesaikan pengalihan tersebut terlebih dahulu.']);
            }

            if (!$flow->isDecisionAllowed($locked, $profile, $decision)) {
                throw ValidationException::withMessages(['handling_decision' => $flow->invalidDecisionMessage($locked, $decision)]);
            }
            if ($conflict = $flow->assessmentConflictMessage($locked, $profile, $decision, $answers)) {
                throw ValidationException::withMessages(['handling_decision' => $conflict]);
            }

            $handover = HandoverRequest::where('asset_id', $locked->id)
                ->where('partner_profile_id', $profile->id)
                ->latest()
                ->first();
            if (($data['final_agreed_value'] ?? null) !== null) {
                if ($handover?->effectiveHandoverType() !== 'sale') {
                    throw ValidationException::withMessages(['final_agreed_value' => 'Nilai akhir hanya dapat dicatat untuk penyerahan dengan penawaran nilai.']);
                }
                if (!$handover?->offers()->where('status', 'accepted')->exists()) {
                    throw ValidationException::withMessages(['final_agreed_value' => 'Belum ada penawaran awal yang diterima warga.']);
                }
            }

            AssetAssessment::create([
                'asset_id' => $locked->id,
                'assessment_type' => 'partner',
                'assessor_user_id' => $request->user()->id,
                'answers_json' => $answers,
                'result_path' => $decision === 'DONATED' ? 'DONATION_READY' : $decision,
                'summary' => $summary,
                'verified_weight_kg' => $locked->verified_weight_kg,
                'verified_at' => now(),
            ]);

            if ($decision === 'CONTINUE_HANDLING') {
                $isContinue = true;
                $locked->update(['status' => 'in_processing']);
                app(AssetEventService::class)->add(
                    $locked,
                    'HANDLING_IN_PROGRESS',
                    'Penanganan masih berlangsung di mitra saat ini',
                    $summary ?: 'Pemeriksaan sudah dicatat. Barang masih ditangani oleh mitra saat ini dan belum memiliki hasil akhir.',
                    ['path' => $decision]
                );
                return;
            }

            $isTransfer = $flow->isTransferDecision($decision);
            if ($isTransfer) {
                $locked->update(['status' => 'needs_transfer']);
                app(AssetEventService::class)->add(
                    $locked,
                    'TRANSFER_RECOMMENDED',
                    'Pemeriksaan selesai — perlu layanan lanjutan',
                    ($summary ? $summary . ' ' : '') . 'Layanan berikutnya: ' . \App\Support\SirkelUi::label($decision) . '.',
                    ['path' => $decision, 'target_capability' => $flow->transferCapabilityForDecision($decision)]
                );
                return;
            }

            // DONATED bukan lagi klaim selesai pada saat assessment. Mitra baru
            // menyatakan barang siap disalurkan; outcome final terkunci setelah bukti
            // foto + waktu + geolokasi penyaluran benar-benar dicatat.
            if ($decision === 'DONATED') {
                $isDonationPending = true;
                $locked->update(['status' => 'awaiting_donation_proof']);
                app(AssetEventService::class)->add(
                    $locked,
                    'DONATION_READY',
                    'Barang siap disalurkan untuk donasi',
                    $summary ?: 'Pemeriksaan menyatakan barang layak masuk tahap penyaluran. Status belum selesai sampai bukti donasi dicatat.',
                    ['path' => 'DONATION_READY']
                );
                return;
            }

            $locked->update([
                'final_path' => $decision,
                'status' => $flow->statusForFinalDecision($decision),
            ]);

            $locked->custody()
                ->where('partner_profile_id', $profile->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            if ($handover && $handover->status !== 'completed') {
                $handover->update(['status' => 'completed']);
            }

            if ($handover && ($data['final_agreed_value'] ?? null) !== null) {
                $offer = $handover->offers()->where('status', 'accepted')->latest('version')->first();
                if ($offer) {
                    $offer->update([
                        'final_agreed_value' => $data['final_agreed_value'],
                        'final_value_reason' => $data['final_value_reason'] ?? null,
                        'final_confirmed_at' => null,
                    ]);
                    app(NotificationService::class)->send(
                        $locked->owner,
                        'Konfirmasi nilai akhir',
                        'Mitra mencatat nilai akhir untuk ' . $locked->passport_code . '. Silakan konfirmasi pada detail barang.',
                        route('user.assets.show', $locked)
                    );
                }
            }

            $verifiedCircular = \App\Support\SirkelUi::isVerifiedOutcome($decision);
            app(AssetEventService::class)->add(
                $locked,
                $verifiedCircular ? 'VERIFIED_OUTCOME' : 'OUTCOME_CLOSED_UNVERIFIED',
                $verifiedCircular ? 'Hasil akhir sirkular terverifikasi' : 'Penanganan ditutup tanpa hasil akhir terverifikasi',
                \App\Support\SirkelUi::label($decision),
                ['path' => $decision]
            );
        });

        if ($isContinue) {
            app(NotificationService::class)->send(
                $asset->owner,
                'Barang masih ditangani mitra',
                'Pemeriksaan ' . $asset->passport_code . ' sudah diperbarui. Barang masih berada pada mitra saat ini dan belum memiliki hasil akhir.',
                route('user.assets.show', $asset)
            );

            return redirect()->route('partner.assets.show', $asset)
                ->with('success', 'Pemeriksaan tersimpan. Barang tetap berada di Barang Ditangani sampai proses selesai atau perlu dialihkan.');
        }

        if ($isTransfer) {
            app(NotificationService::class)->send(
                $asset->owner,
                'Barang memerlukan layanan lanjutan',
                'Pemeriksaan ' . $asset->passport_code . ' selesai. Tahap berikutnya: ' . \App\Support\SirkelUi::label($decision) . '.',
                route('user.assets.show', $asset)
            );

            return redirect()->route('partner.assets.show', $asset)
                ->with('success', 'Pemeriksaan tersimpan. Sekarang pilih mitra lanjutan sesuai layanan yang sudah ditentukan.');
        }

        if ($isDonationPending) {
            app(NotificationService::class)->send(
                $asset->owner,
                'Barang siap disalurkan untuk donasi',
                $asset->passport_code . ' sudah dinyatakan layak disalurkan. Barang tetap berada dalam tanggung jawab mitra sampai bukti penyaluran dicatat.',
                route('user.assets.show', $asset)
            );

            return redirect()->route('partner.assets.show', $asset)
                ->with('success', 'Pemeriksaan tersimpan. Lanjutkan penyaluran lalu unggah Bukti Donasi untuk menyelesaikan outcome.');
        }

        $verifiedCircular = \App\Support\SirkelUi::isVerifiedOutcome($decision);
        app(NotificationService::class)->send(
            $asset->owner,
            $verifiedCircular ? 'Hasil akhir barang diperbarui' : 'Status penanganan barang diperbarui',
            ($verifiedCircular ? 'Hasil akhir untuk ' : 'Status penanganan untuk ') . $asset->passport_code . ': ' . \App\Support\SirkelUi::label($decision),
            route('user.assets.show', $asset)
        );

        return redirect()->route('partner.assets.show', $asset)
            ->with('success', 'Pemeriksaan dan status penanganan berhasil disimpan.');
    }

    public function decisionOptions(Request $request, Asset $asset)
    {
        $profile = $this->profile();
        $this->authorizeHandledAsset($asset, $profile->id);

        abort_unless(
            $asset->custody()->where('partner_profile_id', $profile->id)->whereNull('released_at')->exists(),
            403,
            'Barang ini tidak sedang berada dalam tanggung jawab mitra Anda.'
        );

        $asset->loadMissing('category.group');
        $template = $this->partnerTemplateForAsset($asset);
        $this->normalizeLegacyPartnerAnswers($request, $template);

        $answers = $this->validatePartnerAnswers($request, $template, true);
        $answers += [
            'power_status' => 'unknown',
            'damage_level' => 'unknown',
            'repair_feasible' => 'unknown',
            'hazard_found' => 'unknown',
            'recovery_potential' => 'unknown',
        ];

        $profile->loadMissing('capabilities');
        $flow = app(AssetFlowService::class);

        return response()->json([
            'availability' => $flow->decisionAvailability($asset, $profile, $answers),
            'guidance' => $flow->assessmentGuidance($asset, $profile, $answers),
        ]);
    }

    private function validatePartnerAnswers(Request $request, QuestionnaireTemplate $template, bool $partial = false): array
    {
        $raw = $request->input('answers', []);
        if (!is_array($raw)) {
            throw ValidationException::withMessages(['answers' => 'Jawaban pemeriksaan tidak valid. Muat ulang halaman dan coba lagi.']);
        }

        $answers = [];
        $errors = [];
        foreach ($template->questions->sortBy('sort_order') as $question) {
            $value = $raw[$question->code] ?? null;
            $hasValue = is_array($value) ? count(array_filter($value, fn($item) => $item !== '' && $item !== null)) > 0 : ($value !== '' && $value !== null);

            if (!$partial && $question->required && !$hasValue) {
                $errors['answers.' . $question->code] = 'Pertanyaan “' . $question->text . '” wajib dijawab.';
                continue;
            }
            if (!$hasValue) {
                continue;
            }

            if (in_array($question->type, ['single', 'boolean'], true)) {
                $allowed = $question->options->pluck('value')->map(fn($v) => (string) $v)->all();
                if (!in_array((string) $value, $allowed, true)) {
                    $errors['answers.' . $question->code] = 'Pilihan untuk “' . $question->text . '” tidak valid.';
                    continue;
                }
                $answers[$question->code] = (string) $value;
                continue;
            }

            if ($question->type === 'multi') {
                if (!is_array($value)) {
                    $errors['answers.' . $question->code] = 'Jawaban untuk “' . $question->text . '” tidak valid.';
                    continue;
                }
                $allowed = $question->options->pluck('value')->map(fn($v) => (string) $v)->all();
                $values = array_values(array_unique(array_map('strval', $value)));
                if (array_diff($values, $allowed)) {
                    $errors['answers.' . $question->code] = 'Ada pilihan yang tidak valid pada “' . $question->text . '”.';
                    continue;
                }
                $answers[$question->code] = $values;
                continue;
            }

            $text = trim((string) $value);
            if (mb_strlen($text) > 1600) {
                $errors['answers.' . $question->code] = 'Jawaban untuk “' . $question->text . '” terlalu panjang (maksimal 1600 karakter).';
                continue;
            }
            $answers[$question->code] = $text;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $answers;
    }

    private function partnerTemplateForAsset(Asset $asset): QuestionnaireTemplate
    {
        $template = app(QuestionnaireService::class)->forAsset($asset, 'partner');
        if ($template) {
            return $template;
        }

        // Fallback hanya untuk instalasi lama/test fixture yang belum menjalankan
        // MasterDataSeeder v1.0.28. Instalasi normal selalu memakai template master.
        $template = new QuestionnaireTemplate([
            'code' => 'partner-legacy-fallback',
            'name' => 'Pemeriksaan Mitra — Umum',
            'audience' => 'partner',
            'active' => true,
        ]);

        $definitions = [
            [
                'power_status',
                'Apakah fungsi utama perangkat masih bekerja?',
                [
                    ['normal', 'Ya, berfungsi normal'],
                    ['partial', 'Ya, tetapi bermasalah'],
                    ['off', 'Tidak berfungsi'],
                    ['unknown', 'Belum dapat dipastikan'],
                ]
            ],
            [
                'damage_level',
                'Seberapa berat kerusakan perangkat secara keseluruhan?',
                [
                    ['none', 'Tidak ada kerusakan berarti'],
                    ['minor', 'Ringan'],
                    ['moderate', 'Sedang'],
                    ['severe', 'Berat'],
                    ['unknown', 'Belum dapat dipastikan'],
                ]
            ],
            [
                'repair_feasible',
                'Apakah perangkat masih layak dipertahankan sebagai barang utuh melalui perbaikan/refurbish?',
                [
                    ['yes', 'Ya, layak diperbaiki / direfurbish'],
                    ['no', 'Tidak layak sebagai barang utuh'],
                    ['unknown', 'Belum dapat dipastikan'],
                ]
            ],
        ];

        $questions = collect();
        foreach ($definitions as $index => [$code, $text, $options]) {
            $question = new \App\Models\Question([
                'code' => $code,
                'text' => $text,
                'type' => 'single',
                'required' => true,
                'sort_order' => $index + 1,
                'help_text' => null,
            ]);
            $question->setRelation('options', collect(array_map(
                fn($option, $i) => new \App\Models\QuestionOption([
                    'value' => $option[0],
                    'label' => $option[1],
                    'sort_order' => $i + 1,
                ]),
                $options,
                array_keys($options)
            )));
            $questions->push($question);
        }
        $template->setRelation('questions', $questions);

        return $template;
    }

    private function normalizeLegacyPartnerAnswers(Request $request, QuestionnaireTemplate $template): void
    {
        $answers = $request->input('answers', []);
        if (!is_array($answers)) {
            $answers = [];
        }

        $legacyKeys = ['power_status', 'damage_level', 'repair_feasible', 'hazard_found', 'recovery_potential'];
        $legacySubmission = false;
        foreach ($legacyKeys as $key) {
            if ($request->exists($key)) {
                $legacySubmission = true;
                if (!array_key_exists($key, $answers)) {
                    $answers[$key] = $request->input($key);
                }
            }
        }

        // Form lama sebelum v1.0.28 hanya mengirim tiga jawaban utama. Isi pertanyaan
        // tambahan dengan opsi "unknown" bila tersedia agar request lama tidak gagal,
        // tanpa menebak kondisi teknis yang belum diperiksa.
        if ($legacySubmission) {
            foreach ($template->questions as $question) {
                if (array_key_exists($question->code, $answers)) {
                    continue;
                }
                if ($question->options->contains(fn($option) => (string) $option->value === 'unknown')) {
                    $answers[$question->code] = 'unknown';
                }
            }
        }

        $request->merge(['answers' => $answers]);
    }

    private function profile()
    {
        $profile = auth()->user()->partnerProfile;
        abort_unless($profile && $profile->verification_status === 'approved', 403, 'Mitra belum terverifikasi.');
        return $profile;
    }

    private function authorizeHandledAsset(Asset $asset, int $partnerId): void
    {
        abort_unless(
            $asset->custody()->where('partner_profile_id', $partnerId)->exists(),
            403,
            'Barang ini tidak pernah berada dalam tanggung jawab mitra Anda.'
        );
    }
}
