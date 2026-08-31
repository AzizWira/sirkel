<?php

namespace App\Http\Controllers;

use App\Models\{Asset, AssetAssessment, IntakeSession, IntakeSessionItem, QuestionnaireTemplate};
use App\Services\{AiQuotaService, AssetEventService, IntakeSessionStateService, QuestionnaireService, RuleEngine};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntakeSessionController extends Controller
{
    public function standard(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless($session->isStandard(), 404);
        if ($session->status === IntakeSession::STATUS_REVIEW) return redirect()->route('user.intake.review', $session);
        abort_unless($session->isOpen(), 422, 'Sesi pemeriksaan ini sudah selesai.');

        $session->load(['items.asset.category.group']);
        $items = $session->items->values();
        $currentIndex = max(0, min($items->count() - 1, (int) $session->current_position - 1));
        $item = $items[$currentIndex] ?? null;
        if (! $item) abort(422, 'Sesi tidak memiliki barang untuk diperiksa.');
        if ($item->assessment_completed_at) {
            $next = $items->first(fn ($candidate) => ! $candidate->assessment_completed_at);
            if (! $next) {
                $session->update(['status' => IntakeSession::STATUS_REVIEW]);
                return redirect()->route('user.intake.review', $session);
            }
            $currentIndex = $items->search(fn ($candidate) => $candidate->id === $next->id);
            $session->update(['current_position' => $currentIndex + 1]);
            $item = $next;
        }

        $template = app(QuestionnaireService::class)->forAsset($item->asset, 'citizen');
        abort_unless($template, 422, 'Form cek kondisi belum tersedia untuk kategori barang ini.');

        return view('user.intake.standard', [
            'session' => $session,
            'items' => $items,
            'item' => $item,
            'asset' => $item->asset,
            'template' => $template,
            'answers' => $item->draft_answers_json ?? [],
            'position' => $currentIndex + 1,
            'total' => $items->count(),
            'aiDescriptionQuota' => app(AiQuotaService::class)->status($request->user(), AiQuotaService::CONDITION_DESCRIPTION),
        ]);
    }

    public function autosave(Request $request, IntakeSession $session, IntakeSessionItem $item)
    {
        $this->ownItem($session, $item, $request);
        abort_unless($session->isStandard(), 404);
        $template = app(QuestionnaireService::class)->forAsset($item->asset, 'citizen');
        abort_unless($template, 422);
        $answers = $this->sanitizeTemplateAnswers((array) $request->input('answers', []), $template, false);
        $item->update(['draft_answers_json' => $answers]);
        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function pause(Request $request, IntakeSession $session, IntakeSessionItem $item)
    {
        $this->ownItem($session, $item, $request);
        abort_unless($session->isStandard(), 404);
        $template = app(QuestionnaireService::class)->forAsset($item->asset, 'citizen');
        abort_unless($template, 422);
        $answers = $this->sanitizeTemplateAnswers((array) $request->input('answers', []), $template, false);
        $item->update(['draft_answers_json' => $answers]);
        return redirect()->route('user.cart.index')->with('success', 'Progres cek kondisi disimpan. Anda dapat melanjutkannya kapan saja.');
    }

    public function completeItem(Request $request, IntakeSession $session, IntakeSessionItem $item)
    {
        $this->ownItem($session, $item, $request);
        abort_unless($session->isStandard(), 404);
        abort_if($item->assessment_completed_at, 422, 'Barang ini sudah selesai diperiksa.');

        $asset = $item->asset;
        $template = app(QuestionnaireService::class)->forAsset($asset, 'citizen');
        abort_unless($template, 422, 'Form cek kondisi belum tersedia.');
        $answers = $this->sanitizeTemplateAnswers((array) $request->input('answers', []), $template, true);
        $rule = app(RuleEngine::class)->evaluate($answers, $asset->toArray());

        DB::transaction(function () use ($request, $session, $item, $asset, $answers, $rule) {
            AssetAssessment::updateOrCreate(
                ['asset_id' => $asset->id, 'assessment_type' => 'user', 'assessor_user_id' => $request->user()->id],
                ['answers_json' => $answers, 'result_path' => $rule['path'], 'summary' => $rule['explanation']]
            );
            $asset->update(['preliminary_path' => $rule['path'], 'status' => 'matching']);
            $item->update(['draft_answers_json' => $answers, 'assessment_completed_at' => now()]);
            app(AssetEventService::class)->add($asset, 'PRELIMINARY_ASSESSMENT', 'Cek kondisi selesai', $rule['explanation'], ['path' => $rule['path'], 'intake_session' => $session->public_id]);

            $remaining = $session->items()->whereNull('assessment_completed_at')->orderBy('sort_order')->first();
            if ($remaining) {
                $position = $session->items()->where('sort_order', '<=', $remaining->sort_order)->count();
                $session->update(['current_position' => max(1, $position), 'status' => IntakeSession::STATUS_QUESTIONNAIRE]);
            } else {
                $session->update(['status' => IntakeSession::STATUS_REVIEW, 'completed_at' => now()]);
            }
        });

        $session->refresh();
        if ($session->status === IntakeSession::STATUS_REVIEW) {
            return redirect()->route('user.intake.review', $session)->with('success', 'Cek kondisi seluruh barang selesai. Tinjau rekomendasinya sebelum memilih penyerahan.');
        }
        return redirect()->route('user.intake.standard.show', $session)->with('success', 'Jawaban barang ini tersimpan. Lanjut ke barang berikutnya.');
    }

    public function review(Request $request, IntakeSession $session)
    {
        $this->own($session, $request);
        abort_unless(in_array($session->status, [IntakeSession::STATUS_REVIEW, IntakeSession::STATUS_COMPLETED], true), 422, 'Selesaikan cek kondisi terlebih dahulu.');

        $state = app(IntakeSessionStateService::class);
        $state->reconcile($session);
        $session->load(['items.asset.category.group', 'items.asset.assessments', 'items.asset.requests']);

        $reviewStates = $session->items->mapWithKeys(
            fn (IntakeSessionItem $item) => [$item->id => $state->stateForItem($item)]
        );
        $actionableItems = $state->actionableItems($session);

        return view('user.intake.review', [
            'session' => $session,
            'items' => $session->items,
            'reviewStates' => $reviewStates,
            'actionableItems' => $actionableItems,
        ]);
    }

    private function sanitizeTemplateAnswers(array $raw, QuestionnaireTemplate $template, bool $complete): array
    {
        $answers = [];
        $errors = [];
        foreach ($template->questions->sortBy('sort_order') as $question) {
            $hasValue = array_key_exists($question->code, $raw) && $raw[$question->code] !== '' && $raw[$question->code] !== null && $raw[$question->code] !== [];
            if ($complete && $question->required && ! $hasValue) {
                $errors['answers.'.$question->code] = 'Pertanyaan “'.$question->text.'” wajib dijawab.';
                continue;
            }
            if (! $hasValue) continue;
            $value = $raw[$question->code];
            if (in_array($question->type, ['single', 'boolean'], true)) {
                $allowed = $question->options->pluck('value')->map(fn ($v) => (string) $v)->all();
                if (! in_array((string) $value, $allowed, true)) {
                    $errors['answers.'.$question->code] = 'Pilihan untuk “'.$question->text.'” tidak valid.';
                    continue;
                }
                $answers[$question->code] = (string) $value;
            } elseif ($question->type === 'multi') {
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
            } else {
                $text = trim((string) $value);
                if (mb_strlen($text) > 1600) $errors['answers.'.$question->code] = 'Jawaban terlalu panjang.';
                else $answers[$question->code] = $text;
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
        return $answers;
    }

    private function own(IntakeSession $session, Request $request): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);
    }

    private function ownItem(IntakeSession $session, IntakeSessionItem $item, Request $request): void
    {
        $this->own($session, $request);
        abort_unless($item->intake_session_id === $session->id && $item->asset?->owner_user_id === $request->user()->id, 404);
    }
}
