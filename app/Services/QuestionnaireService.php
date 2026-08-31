<?php

namespace App\Services;

use App\Models\{Asset, QuestionnaireTemplate};
use Illuminate\Support\Collection;

class QuestionnaireService
{
    public function forAsset(Asset $asset, string $audience = 'citizen'): ?QuestionnaireTemplate
    {
        $asset->loadMissing('category.group');

        // Pemeriksaan mitra bersifat berlapis: pertanyaan umum selalu berlaku,
        // kelompok menambah/mengganti yang relevan, lalu kategori menjadi lapisan
        // paling spesifik. Dengan begitu edit template umum di admin ikut berlaku
        // tanpa harus menduplikasi puluhan pertanyaan ke setiap kategori.
        if ($audience === 'partner') {
            return $this->layeredPartnerTemplate($asset);
        }

        // Questionnaire warga mempertahankan perilaku lama: kategori khusus dapat
        // mengganti seluruh template (misalnya baterai memiliki alur keselamatan
        // yang sengaja berbeda dari elektronik umum).
        $base = QuestionnaireTemplate::with(['questions.options', 'deviceCategory', 'deviceGroup'])
            ->where('audience', $audience)
            ->where('active', true);

        $categoryTemplate = (clone $base)
            ->where('device_category_id', $asset->device_category_id)
            ->orderBy('id')
            ->first();
        if ($categoryTemplate) {
            return $categoryTemplate;
        }

        $groupId = $asset->category?->device_group_id;
        if ($groupId) {
            $groupTemplate = (clone $base)
                ->whereNull('device_category_id')
                ->where('device_group_id', $groupId)
                ->orderBy('id')
                ->first();
            if ($groupTemplate) {
                return $groupTemplate;
            }
        }

        return (clone $base)
            ->whereNull('device_category_id')
            ->whereNull('device_group_id')
            ->orderBy('id')
            ->first();
    }

    private function layeredPartnerTemplate(Asset $asset): ?QuestionnaireTemplate
    {
        $base = QuestionnaireTemplate::with(['questions.options', 'deviceCategory', 'deviceGroup'])
            ->where('audience', 'partner')
            ->where('active', true);

        $global = (clone $base)
            ->whereNull('device_category_id')
            ->whereNull('device_group_id')
            ->orderBy('id')
            ->first();

        $group = null;
        $groupId = $asset->category?->device_group_id;
        if ($groupId) {
            $group = (clone $base)
                ->whereNull('device_category_id')
                ->where('device_group_id', $groupId)
                ->orderBy('id')
                ->first();
        }

        $category = (clone $base)
            ->where('device_category_id', $asset->device_category_id)
            ->orderBy('id')
            ->first();

        $selected = $category ?: $group ?: $global;
        if (!$selected) {
            return null;
        }

        /** @var Collection<string, \App\Models\Question> $questions */
        $questions = collect();
        foreach (array_filter([$global, $group, $category]) as $template) {
            foreach ($template->questions as $question) {
                // Template yang lebih spesifik meng-override code yang sama.
                $questions->put($question->code, $question);
            }
        }

        $selected->setRelation(
            'questions',
            $questions->values()->sortBy(fn($question) => [$question->sort_order, $question->id])->values()
        );

        return $selected;
    }
}
