<?php

namespace App\Http\Controllers;

use App\Models\{
    Asset,
    AssetAssessment,
    AssetPhoto,
    DeviceCategory,
    IntakeSession,
    IntakeSessionItem,
    IntakeSessionPhoto,
    User
};
use App\Services\{AiQuotaService, AiService, AssetEventService, IntakeSessionStateService, RuleEngine};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkIntakeController extends Controller
{
    public const MAX_GROUPS = 5;
    public const MAX_QUESTIONS = 15;

    public function create(Request $request, AiQuotaService $quota)
    {
        $sessionState = app(IntakeSessionStateService::class);
        $activeSessions = IntakeSession::query()
            ->with(['items.asset.category', 'items.asset.requests', 'photos'])
            ->where('user_id', $request->user()->id)
            ->where('mode', IntakeSession::MODE_BULK_AI)
            ->whereIn('status', [
                IntakeSession::STATUS_DRAFT,
                IntakeSession::STATUS_QUESTIONNAIRE,
                IntakeSession::STATUS_REVIEW,
            ])
            ->latest('id')
            ->get()
            ->each(fn (IntakeSession $session) => $sessionState->reconcile($session))
            ->filter(fn (IntakeSession $session) => in_array($session->status, [
                IntakeSession::STATUS_DRAFT,
                IntakeSession::STATUS_QUESTIONNAIRE,
                IntakeSession::STATUS_REVIEW,
            ], true))
            ->values();

        return view('user.bulk.create', [
            'quota' => $quota->status($request->user(), AiQuotaService::BULK_AI),
            'activeSessions' => $activeSessions,
        ]);
    }

    public function store(Request $request, AiQuotaService $quota, AiService $ai)
    {
        $request->validate([
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'camera_photos' => 'nullable|array|max:3',
            'camera_photos.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $photos = collect([
            ...$request->file('photos', []),
            ...$request->file('camera_photos', []),
        ])->filter()->values()->all();
        if (count($photos) < 1) {
            throw ValidationException::withMessages(['photos' => 'Tambahkan minimal 1 foto untuk memulai Bulk AI.']);
        }
        if (count($photos) > 3) {
            throw ValidationException::withMessages(['photos' => 'Maksimal 3 foto untuk satu sesi Bulk AI.']);
        }

        if (!$quota->canUse($request->user(), AiQuotaService::BULK_AI)) {
            return back()->withErrors(['photos' => 'Kuota Bulk AI Anda sudah habis. Tambah kuota untuk memulai sesi baru.']);
        }

        $categories = $this->categories();
        $result = $ai->draftBulkIntake($photos, $categories);
        if (!$result) {
            return back()->withErrors(['photos' => $ai->userFacingFailureMessage('Bulk AI belum dapat membaca foto. Coba lagi atau gunakan pendaftaran biasa.')]);
        }
        if (!($result['eligible'] ?? false)) {
            return back()->withErrors(['photos' => $result['eligibility_reason'] ?? 'Foto belum sesuai untuk Bulk AI.']);
        }

        $session = DB::transaction(function () use ($request, $result, $quota, $photos) {
            // Re-check di bawah row lock agar dua tab yang mulai bersamaan tidak dapat
            // menghabiskan satu slot kuota terakhir menjadi dua sesi. AI mungkin sudah
            // dipanggil, tetapi sesi kedua tidak boleh memperoleh kuota yang tidak ada.
            $lockedUser = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (!$quota->canUse($lockedUser, AiQuotaService::BULK_AI)) {
                throw ValidationException::withMessages(['photos' => 'Kuota Bulk AI sudah dipakai oleh sesi lain. Muat ulang halaman Kuota AI sebelum membuat sesi baru.']);
            }

            $session = IntakeSession::create([
                'user_id' => $request->user()->id,
                'mode' => IntakeSession::MODE_BULK_AI,
                'status' => IntakeSession::STATUS_DRAFT,
                'current_position' => 1,
                'bulk_context_json' => [
                    'eligibility_status' => $result['eligibility_status'] ?? 'supported',
                    'eligibility_reason' => $result['eligibility_reason'] ?? '',
                ],
                // Kuota baru dianggap terpakai setelah deteksi AI menghasilkan item yang dapat digunakan.
                'quota_consumed_at' => now(),
            ]);

            foreach ($photos as $index => $file) {
                $path = $file->store('bulk-intake/' . $session->public_id, 'public');
                IntakeSessionPhoto::create([
                    'intake_session_id' => $session->id,
                    'path' => $path,
                    'sort_order' => $index,
                ]);
            }

            foreach (array_slice((array) ($result['items'] ?? []), 0, self::MAX_GROUPS) as $index => $suggestion) {
                $asset = $this->createDraftAsset($request, $suggestion);
                IntakeSessionItem::create([
                    'intake_session_id' => $session->id,
                    'asset_id' => $asset->id,
                    'source' => 'bulk_ai',
                    'sort_order' => $index + 1,
                ]);
            }

            return $session;
        });

        return redirect()->route('user.bulk.edit', $session)
            ->with('success', 'Bulk AI menemukan kelompok barang. Periksa hasilnya, koreksi jika perlu, atau tambahkan barang manual.');
    }

    public function edit(Request $request, IntakeSession $session, AiQuotaService $quota)
    {
        $this->own($session, $request);
        abort_unless($session->isBulk(), 404);
        if ($session->status !== IntakeSession::STATUS_DRAFT) {
            return match ($session->status) {
                IntakeSession::STATUS_QUESTIONNAIRE => redirect()->route('user.bulk.questionnaire', $session)
                    ->with('success', 'Sesi Bulk sudah berada pada tahap pertanyaan. Anda diarahkan ke progres terbaru.'),
                IntakeSession::STATUS_REVIEW => redirect()->route('user.intake.review', $session)
                    ->with('success', 'Sesi Bulk sudah selesai diperiksa. Anda diarahkan ke review terbaru.'),
                default => redirect()->route('user.bulk.create')
                    ->with('success', 'Tahap sesi Bulk sudah berubah. Buka sesi aktif dari halaman Bulk AI.'),
            };
        }
        $session->load(['items.asset.category.group', 'photos']);

        return view('user.bulk.edit', [
            'session' => $session,
            'items' => $session->items,
            'categories' => $this->categories(),
            'maxGroups' => self::MAX_GROUPS,
            'quota' => $quota->status($request->user(), AiQuotaService::BULK_AI),
        ]);
    }

    public function addManual(Request $request, IntakeSession $session)
    {
        $this->ownDraft($session, $request);
        $data = $this->validateItem($request);
        $category = DeviceCategory::findOrFail($data['device_category_id']);
        $this->validateCustomName($category, $data);

        // Dalam mode Bulk, kategori/jenis yang sama digabung ke satu kelompok agar
        // limit 5 menghitung kelompok barang, bukan jumlah unit fisik.
        $same = $this->sameGroupItem($session, $category, $data['custom_item_name'] ?? null);
        if ($same) {
            DB::transaction(function () use ($same, $data, $category) {
                $asset = $same->asset;
                $quantity = min(999, (int) $asset->quantity + (int) $data['quantity']);
                $description = trim((string) $asset->description);
                $addition = trim((string) $data['description']);
                if ($addition !== '' && !str_contains($description, $addition)) {
                    $description = trim($description . "\n" . $addition);
                }
                $asset->update([
                    'quantity' => $quantity,
                    'tracking_type' => $quantity > 1 ? 'batch' : 'individual',
                    'description' => mb_substr($description, 0, 1200),
                    'brand' => $asset->brand ?: ($data['brand'] ?? null),
                    'model_name' => $asset->model_name ?: ($data['model_name'] ?? null),
                    'estimated_weight_kg' => $asset->estimated_weight_kg ?? ($data['estimated_weight_kg'] ?? null),
                    'dormant_since' => $asset->dormant_since ?? ($data['dormant_since'] ?? null),
                ]);
            });
            return back()->with('success', 'Barang sejenis digabung ke kelompok yang sudah ada, sehingga tidak memakai slot baru.');
        }

        if ($session->items()->count() >= self::MAX_GROUPS) {
            throw ValidationException::withMessages(['device_category_id' => 'Maksimal 5 kelompok barang dalam satu sesi Bulk AI.']);
        }

        DB::transaction(function () use ($request, $session, $data) {
            $asset = $this->createDraftAsset($request, $data);
            IntakeSessionItem::create([
                'intake_session_id' => $session->id,
                'asset_id' => $asset->id,
                'source' => 'bulk_manual',
                'sort_order' => ((int) $session->items()->max('sort_order')) + 1,
            ]);
        });

        return back()->with('success', 'Barang manual ditambahkan ke sesi Bulk AI.');
    }

    public function updateItem(Request $request, IntakeSession $session, IntakeSessionItem $item)
    {
        $this->ownDraftItem($session, $item, $request);
        $data = $this->validateItem($request);
        $category = DeviceCategory::findOrFail($data['device_category_id']);
        $this->validateCustomName($category, $data);

        $same = $this->sameGroupItem($session, $category, $data['custom_item_name'] ?? null, $item->id);
        if ($same) {
            throw ValidationException::withMessages([
                'device_category_id' => 'Jenis barang ini sudah ada sebagai kelompok Bulk. Ubah jumlah dan rincian pada kelompok yang sudah ada agar barang sejenis tetap satu kelompok.',
            ]);
        }

        $item->asset->update($data + [
            'tracking_type' => (int) $data['quantity'] > 1 ? 'batch' : 'individual',
        ]);
        return back()->with('success', 'Kelompok barang diperbarui.');
    }

    public function deleteItem(Request $request, IntakeSession $session, IntakeSessionItem $item)
    {
        $this->ownDraftItem($session, $item, $request);
        DB::transaction(function () use ($item) {
            $asset = $item->asset;
            $item->delete();
            $asset->delete();
        });
        return back()->with('success', 'Kelompok barang dihapus dari sesi Bulk AI.');
    }

    public function startQuestionnaire(Request $request, IntakeSession $session, AiService $ai)
    {
        $this->ownDraft($session, $request);
        $count = $session->items()->count();
        abort_if($count < 1 || $count > self::MAX_GROUPS, 422, 'Bulk AI harus berisi 1–5 kelompok barang.');

        $questions = $session->adaptive_questions_json;
        if (!is_array($questions) || $questions === []) {
            $questions = $ai->bulkAdaptiveQuestionnaire($session);
            if (!$questions) {
                return back()->withErrors(['bulk' => $ai->userFacingFailureMessage('AI belum dapat menyusun pertanyaan Bulk. Coba lagi beberapa saat.')]);
            }
        }
        $questions = array_values(array_slice($questions, 0, self::MAX_QUESTIONS));
        $session->update([
            'adaptive_questions_json' => $questions,
            'question_count' => count($questions),
            'status' => IntakeSession::STATUS_QUESTIONNAIRE,
        ]);

        return redirect()->route('user.bulk.questionnaire', $session)
            ->with('success', 'Pertanyaan Bulk AI sudah disusun berdasarkan kombinasi barang Anda.');
    }

    public function questionnaire(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless($session->isBulk() && $session->status === IntakeSession::STATUS_QUESTIONNAIRE, 422, 'Sesi Bulk belum berada pada tahap pertanyaan.');
        $session->load(['items.asset.category']);
        $questions = array_values(array_slice((array) $session->adaptive_questions_json, 0, self::MAX_QUESTIONS));
        abort_if($questions === [], 422, 'Pertanyaan Bulk belum tersedia.');

        return view('user.bulk.questionnaire', [
            'session' => $session,
            'items' => $session->items,
            'questions' => $questions,
            'answers' => $session->adaptive_answers_json ?? [],
        ]);
    }

    public function saveAnswers(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless($session->isBulk() && $session->status === IntakeSession::STATUS_QUESTIONNAIRE, 422);
        $questions = array_values(array_slice((array) $session->adaptive_questions_json, 0, self::MAX_QUESTIONS));
        $answers = $this->sanitizeBulkAnswers((array) $request->input('answers', []), $questions, false);
        $session->update(['adaptive_answers_json' => $answers]);
        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function pause(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless($session->isBulk() && $session->status === IntakeSession::STATUS_QUESTIONNAIRE, 422);
        $questions = array_values(array_slice((array) $session->adaptive_questions_json, 0, self::MAX_QUESTIONS));
        $answers = $this->sanitizeBulkAnswers((array) $request->input('answers', []), $questions, false);
        $session->update(['adaptive_answers_json' => $answers]);
        return redirect()->route('user.bulk.create')->with('success', 'Progres Bulk AI disimpan. Lanjutkan sesi yang sama dari halaman Bulk AI tanpa memakai kuota tambahan.');
    }

    public function complete(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless($session->isBulk() && $session->status === IntakeSession::STATUS_QUESTIONNAIRE, 422);
        $session->load(['items.asset.category', 'photos']);
        $questions = array_values(array_slice((array) $session->adaptive_questions_json, 0, self::MAX_QUESTIONS));
        $answers = $this->sanitizeBulkAnswers((array) $request->input('answers', []), $questions, true);
        $normalized = $this->normalizeSignals($session, $questions, $answers);

        DB::transaction(function () use ($request, $session, $answers, $normalized) {
            foreach ($session->items as $item) {
                $asset = $item->asset;
                $assetAnswers = $normalized[$asset->public_id] ?? [];
                $rule = app(RuleEngine::class)->evaluate($assetAnswers, $asset->toArray());

                AssetAssessment::updateOrCreate(
                    ['asset_id' => $asset->id, 'assessment_type' => 'user', 'assessor_user_id' => $request->user()->id],
                    [
                        'answers_json' => $assetAnswers + ['_bulk_session' => $session->public_id],
                        'result_path' => $rule['path'],
                        'summary' => $rule['explanation'],
                    ]
                );
                $this->attachSessionPhotos($session, $asset);
                $asset->update(['preliminary_path' => $rule['path'], 'status' => 'matching']);
                $item->update(['assessment_completed_at' => now()]);
                app(AssetEventService::class)->add($asset, 'REGISTERED', 'Barang diproses melalui Bulk AI', 'Kelompok barang dikonfirmasi dalam satu sesi Bulk AI.');
                app(AssetEventService::class)->add($asset, 'PRELIMINARY_ASSESSMENT', 'Cek kondisi Bulk selesai', $rule['explanation'], ['path' => $rule['path'], 'intake_session' => $session->public_id]);
            }
            $session->update([
                'adaptive_answers_json' => $answers,
                'status' => IntakeSession::STATUS_REVIEW,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('user.intake.review', $session)
            ->with('success', 'Bulk AI selesai. Tinjau rekomendasi setiap kelompok sebelum memilih penyerahan.');
    }

    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'device_category_id' => 'required|exists:device_categories,id',
            'custom_item_name' => 'nullable|string|max:120',
            'quantity' => 'required|integer|min:1|max:999',
            'description' => 'required|string|min:5|max:1200',
            'brand' => 'nullable|string|max:80',
            'model_name' => 'nullable|string|max:100',
            'estimated_weight_kg' => 'nullable|numeric|min:0|max:9999',
            'dormant_since' => 'nullable|date|before_or_equal:today',
        ]);
        $data['tracking_type'] = ((int) $data['quantity'] > 1) ? 'batch' : 'individual';
        return $data;
    }

    private function validateCustomName(DeviceCategory $category, array $data): void
    {
        if ($category->requiresCustomName() && blank($data['custom_item_name'] ?? null)) {
            throw ValidationException::withMessages(['custom_item_name' => 'Tulis nama barang untuk kategori Lainnya.']);
        }
    }

    private function sameGroupItem(IntakeSession $session, DeviceCategory $category, ?string $customName, ?int $exceptItemId = null): ?IntakeSessionItem
    {
        $normalizedCustom = mb_strtolower(trim((string) $customName));
        $items = $session->items()->with('asset.category')->get();
        return $items->first(function (IntakeSessionItem $candidate) use ($category, $normalizedCustom, $exceptItemId) {
            if ($exceptItemId && $candidate->id === $exceptItemId)
                return false;
            $asset = $candidate->asset;
            if (!$asset || (int) $asset->device_category_id !== (int) $category->id)
                return false;
            if (!$category->requiresCustomName())
                return true;
            return mb_strtolower(trim((string) $asset->custom_item_name)) === $normalizedCustom;
        });
    }

    private function createDraftAsset(Request $request, array $data): Asset
    {
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : (int) ($data['device_category_id'] ?? 0);
        $category = DeviceCategory::findOrFail($categoryId);
        $custom = $data['custom_item_name'] ?? null;
        if ($category->requiresCustomName() && blank($custom))
            $custom = $data['detected_name'] ?? $category->name;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        return Asset::create([
            'owner_user_id' => $request->user()->id,
            'passport_code' => 'SRK-B-' . now()->format('ymd') . '-' . strtoupper(str()->random(6)),
            'device_category_id' => $category->id,
            'tracking_type' => $quantity > 1 ? 'batch' : 'individual',
            'custom_item_name' => $custom,
            'brand' => $data['brand'] ?? null,
            'model_name' => $data['model_name'] ?? null,
            'estimated_weight_kg' => $data['estimated_weight_kg'] ?? null,
            'dormant_since' => $data['dormant_since'] ?? null,
            'description' => $data['description'] ?? 'Kondisi akan dikonfirmasi pada sesi Bulk AI.',
            'quantity' => $quantity,
            'status' => 'bulk_draft',
            'origin_district' => $request->user()->district,
            'origin_village' => $request->user()->village,
        ]);
    }

    private function attachSessionPhotos(IntakeSession $session, Asset $asset): void
    {
        if ($asset->photos()->exists())
            return;
        $session->loadMissing('photos');
        foreach ($session->photos as $index => $photo) {
            AssetPhoto::create([
                'asset_id' => $asset->id,
                'path' => $photo->path,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }

    private function sanitizeBulkAnswers(array $raw, array $questions, bool $complete): array
    {
        $clean = [];
        $errors = [];
        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');
            if ($id === '')
                continue;
            $value = $raw[$id] ?? null;
            $required = (bool) ($question['required'] ?? true);
            $type = (string) ($question['type'] ?? 'single');
            $targets = array_values(array_map('strval', (array) ($question['targets'] ?? [])));
            $allowed = array_values(array_map(fn($o) => (string) ($o['value'] ?? ''), (array) ($question['options'] ?? [])));

            if (str_starts_with($type, 'matrix_')) {
                $value = is_array($value) ? $value : [];
                $matrix = [];
                foreach ($targets as $target) {
                    $targetValue = $value[$target] ?? null;
                    if ($type === 'matrix_multi') {
                        $values = is_array($targetValue) ? array_values(array_unique(array_map('strval', $targetValue))) : [];
                        if ($complete && $required && $values === [])
                            $errors['answers.' . $id . '.' . $target] = 'Jawaban untuk setiap barang pada pertanyaan ini wajib dipilih.';
                        elseif (array_diff($values, $allowed))
                            $errors['answers.' . $id . '.' . $target] = 'Ada pilihan Bulk yang tidak valid.';
                        elseif ((in_array('none', $values, true) || in_array('unknown', $values, true)) && count($values) > 1)
                            $errors['answers.' . $id . '.' . $target] = 'Pilihan “tidak ada/tidak tahu” tidak dapat digabung dengan kondisi lain.';
                        elseif ($values !== [])
                            $matrix[$target] = $values;
                    } else {
                        $scalar = is_scalar($targetValue) ? (string) $targetValue : '';
                        if ($complete && $required && $scalar === '')
                            $errors['answers.' . $id . '.' . $target] = 'Jawaban untuk setiap barang pada pertanyaan ini wajib dipilih.';
                        elseif ($scalar !== '' && !in_array($scalar, $allowed, true))
                            $errors['answers.' . $id . '.' . $target] = 'Pilihan Bulk tidak valid.';
                        elseif ($scalar !== '')
                            $matrix[$target] = $scalar;
                    }
                }
                if ($matrix !== [])
                    $clean[$id] = $matrix;
                continue;
            }

            if ($type === 'multi') {
                $values = is_array($value) ? array_values(array_unique(array_map('strval', $value))) : [];
                if ($complete && $required && $values === [])
                    $errors['answers.' . $id] = 'Pertanyaan ini wajib dijawab.';
                elseif (array_diff($values, $allowed))
                    $errors['answers.' . $id] = 'Ada pilihan Bulk yang tidak valid.';
                elseif ((in_array('none', $values, true) || in_array('unknown', $values, true)) && count($values) > 1)
                    $errors['answers.' . $id] = 'Pilihan “tidak ada/tidak tahu” tidak dapat digabung dengan kondisi lain.';
                elseif ($values !== [])
                    $clean[$id] = $values;
                continue;
            }

            if ($type === 'text') {
                $text = trim((string) ($value ?? ''));
                if ($complete && $required && $text === '')
                    $errors['answers.' . $id] = 'Pertanyaan ini wajib dijawab.';
                elseif (mb_strlen($text) > 1200)
                    $errors['answers.' . $id] = 'Jawaban terlalu panjang.';
                elseif ($text !== '')
                    $clean[$id] = $text;
                continue;
            }

            $scalar = is_scalar($value) ? (string) $value : '';
            if ($complete && $required && $scalar === '')
                $errors['answers.' . $id] = 'Pertanyaan ini wajib dijawab.';
            elseif ($scalar !== '' && !in_array($scalar, $allowed, true))
                $errors['answers.' . $id] = 'Pilihan Bulk tidak valid.';
            elseif ($scalar !== '')
                $clean[$id] = $scalar;
        }

        if ($errors)
            throw ValidationException::withMessages($errors);
        return $clean;
    }

    private function normalizeSignals(IntakeSession $session, array $questions, array $answers): array
    {
        $session->loadMissing('items.asset');
        $result = [];
        foreach ($session->items as $item)
            $result[$item->asset->public_id] = [];

        $hazardKeys = ['battery_swollen', 'battery_leaking', 'cooling_leak', 'burn_damage'];
        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');
            if (!array_key_exists($id, $answers))
                continue;
            $type = (string) ($question['type'] ?? 'single');
            $targets = array_values(array_filter(array_map('strval', (array) ($question['targets'] ?? [])), fn($target) => isset($result[$target])));
            $signalKey = $question['signal_key'] ?? null;
            $signalMap = (array) ($question['signal_map'] ?? []);
            $value = $answers[$id];

            if ($type === 'matrix_single') {
                foreach ($targets as $target) {
                    if ($signalKey && isset($value[$target]))
                        $result[$target][$signalKey] = (string) $value[$target];
                }
            } elseif ($type === 'matrix_multi') {
                foreach ($targets as $target) {
                    $selected = array_map('strval', (array) ($value[$target] ?? []));
                    $this->applyMultiSignals($result[$target], $selected, $signalMap);
                }
            } elseif ($type === 'multi') {
                $selected = array_map('strval', (array) $value);
                foreach ($targets as $target) {
                    $this->applyMultiSignals($result[$target], $selected, $signalMap);
                }
            } elseif ($type === 'text') {
                foreach ($targets as $target) {
                    $result[$target]['bulk_notes'] = trim(($result[$target]['bulk_notes'] ?? '') . ' ' . (string) $value);
                }
            } elseif ($signalKey) {
                foreach ($targets as $target)
                    $result[$target][$signalKey] = (string) $value;
            }
        }

        foreach ($result as &$signals) {
            $hasHazard = false;
            foreach ($hazardKeys as $key) {
                if (($signals[$key] ?? 'no') === 'yes') {
                    $hasHazard = true;
                    break;
                }
            }
            if ($hasHazard)
                $signals['hazard_sign'] = 'yes';
            elseif (!isset($signals['hazard_sign']))
                $signals['hazard_sign'] = 'no';
        }
        unset($signals);
        return $result;
    }

    private function applyMultiSignals(array &$signals, array $selected, array $signalMap): void
    {
        $mappedSignals = array_values(array_unique(array_filter(
            array_values($signalMap),
            fn($signal) => $signal !== 'none' && $signal !== ''
        )));
        $unknown = in_array('unknown', $selected, true);
        $none = in_array('none', $selected, true);

        foreach ($mappedSignals as $signal) {
            $matched = false;
            foreach ($signalMap as $option => $mapped) {
                if ($mapped === $signal && in_array((string) $option, $selected, true)) {
                    $matched = true;
                    break;
                }
            }
            $signals[$signal] = $matched ? 'yes' : ($unknown ? 'unknown' : 'no');
            if ($none)
                $signals[$signal] = 'no';
        }
    }

    private function categories()
    {
        return DeviceCategory::with('group')->where('active', true)->get()
            ->sortBy(fn($category) => sprintf('%03d-%03d', $category->group?->sort_order ?? 999, $category->sort_order))->values();
    }

    private function own(IntakeSession $session, Request $request): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);
    }

    private function ownDraft(IntakeSession $session, Request $request): void
    {
        $this->own($session, $request);
        abort_unless($session->isBulk() && $session->status === IntakeSession::STATUS_DRAFT, 422, 'Sesi Bulk ini sudah tidak dapat diedit.');
    }

    private function ownDraftItem(IntakeSession $session, IntakeSessionItem $item, Request $request): void
    {
        $this->ownDraft($session, $request);
        abort_unless($item->intake_session_id === $session->id && $item->asset?->owner_user_id === $request->user()->id, 404);
    }
}
