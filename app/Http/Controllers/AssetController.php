<?php

namespace App\Http\Controllers;

use App\Models\{Asset, AssetAssessment, AssetPhoto, DeviceCategory, HandoverRequest, IssueReport, QuestionnaireTemplate};
use App\Services\{AiQuotaService, AiService, AssetEventService, OfferLifecycleService, RegionService, RuleEngine};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function index()
    {
        return view('user.assets.index', [
            'assets' => Asset::with('category')->where('owner_user_id', auth()->id())->whereNotIn('status', ['cart','bulk_draft'])->latest()->paginate(12),
        ]);
    }

    public function create()
    {
        return view('user.assets.create', [
            'categories' => DeviceCategory::with('group')->where('active', true)->get()->sortBy(fn ($category) => sprintf('%03d-%03d', $category->group?->sort_order ?? 999, $category->sort_order))->values(),
            'districts' => app(RegionService::class)->surabayaDistricts(),
            'aiQuota' => app(AiQuotaService::class)->status(auth()->user(), AiQuotaService::ASSET_INTAKE),
        ]);
    }

    public function aiDraft(Request $request)
    {
        $request->validate([
            'photos' => 'required|array|min:1|max:3',
            'photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $quota = app(AiQuotaService::class);
        if (! $quota->canUse($request->user(), AiQuotaService::ASSET_INTAKE)) {
            return response()->json([
                'message' => 'Kuota Pengenalan Barang Anda sudah habis. Tambah kuota untuk menggunakan fitur ini kembali.',
                'quota_exhausted' => true,
                'topup_url' => route('user.ai-quota.index'),
                'quota' => $quota->status($request->user(), AiQuotaService::ASSET_INTAKE),
            ], 403);
        }

        $categories = DeviceCategory::with('group')
            ->where('active', true)
            ->get()
            ->sortBy(fn ($category) => sprintf('%03d-%03d', $category->group?->sort_order ?? 999, $category->sort_order))
            ->values();

        $ai = app(AiService::class);
        $suggestion = $ai->draftIntake($request->file('photos', []), $categories);
        if (! $suggestion) {
            return response()->json([
                'message' => $ai->userFacingFailureMessage('Bantuan AI belum dapat digunakan saat ini. Anda tetap dapat mengisi data secara manual.'),
            ], 503);
        }

        return response()->json([
            'suggestion' => $suggestion,
            'notice' => 'Periksa saran yang tersedia lalu pilih bagian yang ingin digunakan.',
            'quota' => $quota->status($request->user(), AiQuotaService::ASSET_INTAKE),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'photo_scope_status' => 'nullable|in:unknown,single_type,multiple_types,uncertain',
        ]);
        if ($request->input('photo_scope_status') === 'multiple_types') {
            return back()->withErrors([
                'photos' => 'Pendaftaran biasa hanya untuk satu jenis barang. Pilih foto satu jenis barang saja atau gunakan Bulk AI untuk beberapa jenis sekaligus.',
            ])->withInput();
        }

        $data = $request->validate([
            'device_category_id' => 'required|exists:device_categories,id',
            'tracking_type' => 'required|in:individual,batch',
            'custom_item_name' => 'nullable|string|max:120',
            'brand' => 'nullable|string|max:80',
            'model_name' => 'nullable|string|max:100',
            'description' => 'required|string|min:10|max:1200',
            'quantity' => 'required|integer|min:1|max:999',
            'condition_class' => 'nullable|string|max:60',
            'estimated_weight_kg' => 'nullable|numeric|min:0|max:9999',
            'dormant_since' => 'nullable|date',
            'origin_district' => 'required|string|max:100',
            'origin_village' => 'required|string|max:100',
            'photos' => 'required|array|min:1|max:3',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $regionService = app(RegionService::class);
        if (! $regionService->isValidSurabayaLocation($data['origin_district'], $data['origin_village'])) {
            throw ValidationException::withMessages([
                'origin_village' => 'Kelurahan asal tidak sesuai dengan kecamatan yang dipilih.',
            ]);
        }
        $normalized = $regionService->normalizeLocation($data['origin_district'], $data['origin_village']);
        $data['origin_district'] = $normalized['district'];
        $data['origin_village'] = $normalized['village'];

        $category = DeviceCategory::findOrFail($data['device_category_id']);
        if ($data['tracking_type'] === 'batch' && ! $category->supports_batch) {
            return back()->withErrors(['tracking_type' => 'Kategori ini belum mendukung pencatatan sebagai kelompok barang.'])->withInput();
        }
        if ($data['tracking_type'] === 'individual') {
            $data['quantity'] = 1;
        }
        if ($category->requiresCustomName() && blank($data['custom_item_name'] ?? null)) {
            return back()->withErrors(['custom_item_name' => 'Tulis nama barang agar SIRKEL dan mitra dapat mengenalinya.'])->withInput();
        }

        $saveToCart = $request->boolean('save_to_cart');

        $asset = DB::transaction(function () use ($request, $data, $saveToCart) {
            $asset = Asset::create($data + [
                'owner_user_id' => $request->user()->id,
                'passport_code' => 'SRK-'.strtoupper($data['tracking_type'][0]).'-'.now()->format('ymd').'-'.strtoupper(str()->random(6)),
                'status' => $saveToCart ? 'cart' : 'registered',
            ]);

            foreach ($request->file('photos', []) as $i => $file) {
                $path = $file->store('assets/'.$asset->id, 'public');
                AssetPhoto::create([
                    'asset_id' => $asset->id,
                    'path' => $path,
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }

            if ($saveToCart) {
                app(AssetEventService::class)->add($asset, 'CART_ADDED', 'Disimpan ke Keranjang', 'Barang elektronik disimpan ke Keranjang untuk diproses bersama barang lain.');
            } else {
                app(AssetEventService::class)->add($asset, 'REGISTERED', 'Barang didaftarkan', 'Barang elektronik masuk ke SIRKEL.');
            }
            return $asset;
        });

        // Analisis foto AI bersifat opt-in di form pendaftaran. Menyimpan barang tidak
        // boleh diam-diam mengirim foto ke AI atau mengubah data yang telah disetujui warga.
        if ($saveToCart) {
            return redirect()->route('user.cart.index')->with('success', 'Barang disimpan ke Keranjang. Anda bebas menambah barang lain sebelum memilih maksimal 3 untuk diproses.');
        }

        // Fallback kompatibilitas untuk request lama/test/integrasi yang belum mengirim save_to_cart.
        return redirect()->route('user.assets.assessment', $asset)->with('success', 'Barang berhasil didaftarkan. Lanjutkan cek kondisi.');
    }

    public function show(Asset $asset)
    {
        $this->own($asset);
        if ($asset->status === 'cart') return redirect()->route('user.cart.index');
        abort_if($asset->status === 'bulk_draft', 404);
        foreach ($asset->requests()->pluck('id') as $requestId) {
            app(OfferLifecycleService::class)->expireOverdue((int) $requestId);
        }
        $asset->refresh();
        $asset->load([
            'category.group',
            'photos',
            'assessments.assessor',
            'events',
            'activeRequest.partner',
            'activeRequest.currentOffer',
            'activeRequest.offers',
            'latestRequest.partner',
            'latestRequest.currentOffer',
            'latestRequest.offers',
            'custody.partner',
            'transfers.fromPartner',
            'transfers.toPartner',
            'donationProof.partner',
        ]);

        $template = $this->assessmentTemplate($asset);
        $partnerTemplate = app(\App\Services\QuestionnaireService::class)->forAsset($asset, 'partner');
        $matchingHelpIssue = IssueReport::query()
            ->with('request.partner')
            ->where('reporter_user_id', auth()->id())
            ->where('asset_id', $asset->id)
            ->where('category', 'matching_help')
            ->whereNotNull('context_json')
            ->latest()
            ->first();

        return view('user.assets.show', [
            'asset' => $asset,
            'questionMap' => $template?->questions?->keyBy('code') ?? collect(),
            'partnerQuestionMap' => $partnerTemplate?->questions?->keyBy('code') ?? collect(),
            'matchingHelpIssue' => $matchingHelpIssue,
        ]);
    }

    public function assessment(Asset $asset)
    {
        $this->own($asset);
        $this->assertAssessmentEditable($asset);

        $template = $this->assessmentTemplate($asset);
        abort_unless($template, 422, 'Form cek kondisi belum tersedia untuk kategori barang ini.');

        return view('user.assets.assessment', [
            'asset' => $asset,
            'template' => $template,
            'aiDescriptionQuota' => app(AiQuotaService::class)->status(auth()->user(), AiQuotaService::CONDITION_DESCRIPTION),
        ]);
    }

    public function submitAssessment(Request $request, Asset $asset)
    {
        $this->own($asset);
        $this->assertAssessmentEditable($asset);
        $template = $this->assessmentTemplate($asset);
        abort_unless($template, 422, 'Form cek kondisi belum tersedia untuk kategori barang ini.');

        $answers = $this->validateAssessmentAnswers($request, $template);
        $rule = app(RuleEngine::class)->evaluate($answers, $asset->toArray());

        AssetAssessment::create([
            'asset_id' => $asset->id,
            'assessment_type' => 'user',
            'assessor_user_id' => $request->user()->id,
            'answers_json' => $answers,
            'result_path' => $rule['path'],
            'summary' => $rule['explanation'],
        ]);
        $asset->update(['preliminary_path' => $rule['path'], 'status' => 'matching']);

        app(AssetEventService::class)->add(
            $asset,
            'PRELIMINARY_ASSESSMENT',
            'Cek kondisi selesai',
            $rule['explanation'],
            ['path' => $rule['path']]
        );

        return redirect()->route('user.handovers.match.form', $asset)
            ->with('success', 'Rekomendasi awal selesai. Sekarang pilih tujuan dan cara penyerahan.');
    }

    public function destroy(Asset $asset)
    {
        $this->own($asset);
        if ($asset->core_locked_at || $asset->custody()->whereNull('released_at')->exists()) {
            abort(422, 'Barang sudah diterima mitra dan tidak dapat dihapus dari riwayat.');
        }
        if ($asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists()) {
            abort(422, 'Batalkan permintaan yang masih aktif sebelum menghapus barang.');
        }
        $asset->delete();

        return redirect()->route('user.assets.index')->with('success', 'Barang dihapus.');
    }

    private function assessmentTemplate(Asset $asset): ?QuestionnaireTemplate
    {
        return app(\App\Services\QuestionnaireService::class)->forAsset($asset, 'citizen');
    }

    private function validateAssessmentAnswers(Request $request, QuestionnaireTemplate $template): array
    {
        $raw = $request->input('answers', []);
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['answers' => 'Jawaban cek kondisi tidak valid. Muat ulang halaman dan coba lagi.']);
        }

        $answers = [];
        $errors = [];
        foreach ($template->questions->sortBy('sort_order') as $question) {
            $hasValue = array_key_exists($question->code, $raw) && $raw[$question->code] !== '' && $raw[$question->code] !== null;
            if ($question->required && ! $hasValue) {
                $errors['answers.'.$question->code] = 'Pertanyaan “'.$question->text.'” wajib dijawab.';
                continue;
            }
            if (! $hasValue) {
                continue;
            }

            $value = $raw[$question->code];
            if (in_array($question->type, ['single', 'boolean'], true)) {
                $allowed = $question->options->pluck('value')->map(fn ($v) => (string) $v)->all();
                if (! in_array((string) $value, $allowed, true)) {
                    $errors['answers.'.$question->code] = 'Pilihan untuk “'.$question->text.'” tidak valid.';
                    continue;
                }
                $answers[$question->code] = (string) $value;
                continue;
            }

            if ($question->type === 'multi') {
                if (! is_array($value)) {
                    $errors['answers.'.$question->code] = 'Jawaban untuk “'.$question->text.'” tidak valid.';
                    continue;
                }
                $allowed = $question->options->pluck('value')->map(fn ($v) => (string) $v)->all();
                $values = array_values(array_unique(array_map('strval', $value)));
                if (array_diff($values, $allowed)) {
                    $errors['answers.'.$question->code] = 'Ada pilihan yang tidak valid pada “'.$question->text.'”.';
                    continue;
                }
                $answers[$question->code] = $values;
                continue;
            }

            $text = trim((string) $value);
            if (mb_strlen($text) > 1600) {
                $errors['answers.'.$question->code] = 'Jawaban untuk “'.$question->text.'” terlalu panjang (maksimal 1600 karakter).';
                continue;
            }
            $answers[$question->code] = $text;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $answers;
    }

    private function assertAssessmentEditable(Asset $asset): void
    {
        abort_if(in_array($asset->status, ['cart', 'bulk_draft'], true), 422, 'Pilih barang dari Keranjang terlebih dahulu sebelum memulai cek kondisi.');
        abort_if($asset->final_path, 422, 'Barang ini sudah memiliki hasil akhir dan cek kondisi warga tidak dapat diubah.');
        abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra. Pemeriksaan berikutnya dilakukan oleh mitra, bukan melalui cek kondisi warga.');
        abort_if(
            $asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists(),
            422,
            'Barang sudah memiliki permintaan penyerahan aktif. Batalkan permintaan terlebih dahulu jika ingin mengulang cek kondisi.'
        );
    }

    private function own(Asset $asset): void
    {
        abort_unless($asset->owner_user_id === auth()->id(), 403);
    }
}
