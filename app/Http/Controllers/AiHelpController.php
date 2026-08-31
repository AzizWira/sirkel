<?php

namespace App\Http\Controllers;

use App\Models\{Asset, QuestionnaireTemplate};
use App\Services\{AiQuotaService, AiService};
use Illuminate\Http\Request;

class AiHelpController extends Controller
{
    public function conditionDescription(Request $request, Asset $asset)
    {
        abort_unless($asset->owner_user_id === $request->user()->id, 403);

        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $asset->loadMissing('category');
        $template = $this->assessmentTemplate($asset);
        abort_unless($template, 422, 'Form cek kondisi belum tersedia untuk kategori barang ini.');

        $raw = $data['answers'];
        $readable = [];
        $missing = [];

        foreach ($template->questions->sortBy('sort_order') as $question) {
            if ($question->code === 'notes')
                continue;

            $value = $raw[$question->code] ?? null;
            $hasValue = is_array($value) ? count(array_filter($value, fn($item) => $item !== '' && $item !== null)) > 0 : filled($value);
            if ($question->required && !$hasValue) {
                $missing[] = $question->text;
                continue;
            }
            if (!$hasValue)
                continue;

            if ($question->type === 'multi') {
                $values = array_values(array_unique(array_map('strval', (array) $value)));
                $known = $question->options->keyBy(fn($option) => (string) $option->value);
                if (collect($values)->contains(fn($item) => !$known->has($item))) {
                    return response()->json(['message' => 'Ada jawaban yang tidak valid. Muat ulang halaman lalu coba lagi.'], 422);
                }
                $answerLabel = collect($values)->map(fn($item) => $known->get($item)?->label ?: $item)->implode(', ');
            } elseif (in_array($question->type, ['single', 'boolean'], true)) {
                $option = $question->options->firstWhere('value', (string) $value);
                if (!$option) {
                    return response()->json(['message' => 'Ada jawaban yang tidak valid. Muat ulang halaman lalu coba lagi.'], 422);
                }
                $answerLabel = $option->label;
            } else {
                $answerLabel = mb_substr(trim((string) $value), 0, 800);
            }

            $readable[] = [
                'kode' => $question->code,
                'pertanyaan' => $question->text,
                'nilai' => is_array($value) ? array_values($value) : (string) $value,
                'jawaban' => $answerLabel,
            ];
        }

        if ($missing) {
            return response()->json([
                'message' => 'Lengkapi semua pertanyaan wajib terlebih dahulu sebelum membuat deskripsi dengan AI.',
                'missing' => $missing,
            ], 422);
        }

        $quota = app(AiQuotaService::class);
        if (!$quota->canUse($request->user(), AiQuotaService::CONDITION_DESCRIPTION)) {
            return response()->json([
                'message' => 'Kuota Penyusunan Catatan Kondisi Anda sudah habis. Tambah kuota atau tulis catatan secara manual.',
                'quota_exhausted' => true,
                'topup_url' => route('user.ai-quota.index'),
                'quota' => $quota->status($request->user(), AiQuotaService::CONDITION_DESCRIPTION),
            ], 403);
        }

        $ai = app(AiService::class);
        $description = $ai->citizenConditionDescription($asset, $readable);
        if (!filled($description)) {
            return response()->json([
                'message' => $ai->userFacingFailureMessage('Deskripsi belum dapat dibuat saat ini. Anda tetap dapat menulis kondisi secara manual.'),
            ], 503);
        }

        return response()->json([
            'description' => $description,
            'generated_from' => count($readable),
            'quota' => $quota->status($request->user(), AiQuotaService::CONDITION_DESCRIPTION),
        ]);
    }

    private function assessmentTemplate(Asset $asset): ?QuestionnaireTemplate
    {
        return app(\App\Services\QuestionnaireService::class)->forAsset($asset, 'citizen');
    }
}
