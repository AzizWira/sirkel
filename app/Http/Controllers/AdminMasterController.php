<?php

namespace App\Http\Controllers;

use App\Models\{CircularRule, DeviceCategory, DeviceGroup, Question, QuestionnaireTemplate};
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class AdminMasterController extends Controller
{
    public function index()
    {
        $templates = QuestionnaireTemplate::with(['questions.options', 'deviceCategory.group', 'deviceGroup'])
            ->orderBy('audience')
            ->orderBy('name')
            ->get();

        // Aturan rekomendasi warga hanya boleh dibangun dari pertanyaan cek kondisi warga.
        $ruleFields = $templates->where('audience', 'citizen')->flatMap->questions
            ->groupBy('code')
            ->map(function ($questions, $code) {
                $first = $questions->first();
                $options = $questions->flatMap->options
                    ->unique('value')
                    ->values()
                    ->map(fn($o) => ['value' => $o->value, 'label' => $o->label])
                    ->all();

                return [
                    'code' => $code,
                    'label' => $first->text,
                    'options' => $options,
                    'deterministic' => $questions->every(fn($question) => $question->type !== 'text') && count($options) > 0,
                ];
            })
            ->filter(fn($field) => $field['deterministic'])
            ->values();

        return view('admin.master.index', [
            'groups' => DeviceGroup::with(['categories' => fn($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'templates' => $templates,
            'citizenTemplates' => $templates->where('audience', 'citizen')->values(),
            'partnerTemplates' => $templates->where('audience', 'partner')->values(),
            'rules' => CircularRule::orderBy('priority')->get(),
            'ruleFields' => $ruleFields,
        ]);
    }

    public function group(Request $request, ?DeviceGroup $group = null)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('device_groups', 'code')->ignore($group?->id)],
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:1|max:9999',
            'active' => 'nullable|boolean',
        ]);

        $before = $group?->toArray();
        $group ??= new DeviceGroup;
        $group->fill([
            'code' => $data['code'] ?: ($group->code ?: Str::slug($data['name'])),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($group->sort_order ?: ((int) DeviceGroup::max('sort_order') + 1)),
            'active' => $request->boolean('active'),
        ]);
        $group->save();

        app(AuditService::class)->log('master.group.save', $group, $before, $group->toArray());
        return back()->with('success', 'Kelompok elektronik disimpan.');
    }

    public function category(Request $request, ?DeviceCategory $category = null)
    {
        $data = $request->validate([
            'device_group_id' => 'required|exists:device_groups,id',
            'code' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('device_categories', 'code')->ignore($category?->id)],
            'name' => 'required|string|max:120',
            'supports_batch' => 'nullable|boolean',
            'special_handling_possible' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:9999',
        ]);

        $before = $category?->toArray();
        $category ??= new DeviceCategory;
        $category->fill([
            'device_group_id' => $data['device_group_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?: ($category->code ?: Str::slug($data['name'])),
            'supports_batch' => $request->boolean('supports_batch'),
            'special_handling_possible' => $request->boolean('special_handling_possible'),
            'active' => $request->boolean('active'),
            'sort_order' => $data['sort_order'] ?? ($category->sort_order ?: ((int) DeviceCategory::max('sort_order') + 1)),
        ]);
        $category->save();

        app(AuditService::class)->log('master.category.save', $category, $before, $category->toArray());
        return back()->with('success', 'Kategori barang disimpan.');
    }

    public function template(Request $request)
    {
        $templateId = $request->input('template_id');
        $template = $templateId ? QuestionnaireTemplate::findOrFail($templateId) : new QuestionnaireTemplate;

        $data = $request->validate([
            'template_id' => 'nullable|exists:questionnaire_templates,id',
            'audience' => 'required|in:citizen,partner',
            'device_group_id' => 'nullable|exists:device_groups,id',
            'device_category_id' => 'nullable|exists:device_categories,id',
            'code' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('questionnaire_templates', 'code')->ignore($template->id)],
            'name' => 'required|string|max:150',
            'active' => 'nullable|boolean',
        ]);

        if (!empty($data['device_group_id']) && !empty($data['device_category_id'])) {
            throw ValidationException::withMessages([
                'device_category_id' => 'Pilih salah satu cakupan: kelompok atau kategori, bukan keduanya sekaligus.',
            ]);
        }

        if (!empty($data['device_category_id'])) {
            $category = DeviceCategory::findOrFail($data['device_category_id']);
            // Kategori sudah menentukan kelompok; jangan simpan scope ganda.
            $data['device_group_id'] = null;
        }

        $before = $template->exists ? $template->toArray() : null;
        $template->fill([
            'audience' => $data['audience'],
            'device_group_id' => $data['device_group_id'] ?? null,
            'device_category_id' => $data['device_category_id'] ?? null,
            'code' => $data['code'] ?: ($template->code ?: Str::slug($data['name'])),
            'name' => $data['name'],
            'active' => $request->boolean('active'),
        ]);
        $template->save();

        app(AuditService::class)->log('master.questionnaire.save', $template, $before, $template->toArray());
        return back()->with('success', 'Template pemeriksaan disimpan.');
    }

    public function question(Request $request, QuestionnaireTemplate $template)
    {
        $questionId = $request->input('question_id');
        $question = $questionId
            ? Question::where('questionnaire_template_id', $template->id)->findOrFail($questionId)
            : new Question(['questionnaire_template_id' => $template->id]);

        $data = $request->validate([
            'question_id' => 'nullable|exists:questions,id',
            'code' => 'nullable|string|max:100|alpha_dash',
            'text' => 'required|string|max:500',
            'type' => 'required|in:single,multi,text,boolean',
            'required' => 'nullable|boolean',
            'help_text' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:1|max:9999',
            'options_text' => 'nullable|string|max:2000',
            'option_labels' => 'nullable|array|max:30',
            'option_labels.*' => 'nullable|string|max:180',
            'option_values' => 'nullable|array|max:30',
            'option_values.*' => 'nullable|string|max:100',
        ]);

        $before = $question->exists ? $question->load('options')->toArray() : null;
        $question->fill([
            'code' => $data['code'] ?: ($question->code ?: Str::slug($data['text'], '_')),
            'text' => $data['text'],
            'type' => $data['type'],
            'required' => $request->boolean('required'),
            'help_text' => $data['help_text'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($question->sort_order ?: ((int) $template->questions()->max('sort_order') + 1)),
        ]);
        $question->save();

        $question->options()->delete();
        if ($data['type'] === 'boolean') {
            $question->options()->create(['value' => 'yes', 'label' => 'Ya', 'sort_order' => 1]);
            $question->options()->create(['value' => 'no', 'label' => 'Tidak', 'sort_order' => 2]);
        } elseif (in_array($data['type'], ['single', 'multi'], true)) {
            $labels = collect($data['option_labels'] ?? [])
                ->map(fn($label) => trim((string) $label))
                ->filter()
                ->values();
            $values = collect($data['option_values'] ?? [])->values();

            // Kompatibilitas request lama sebelum UI editor pilihan diperbaiki.
            if ($labels->isEmpty() && filled($data['options_text'] ?? null)) {
                $legacy = collect(preg_split('/\r?\n/', trim((string) $data['options_text'])))
                    ->map(function ($line) {
                        if (!trim($line))
                            return null;
                        if (str_contains($line, '|')) {
                            [$value, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                            return ['value' => $value, 'label' => $label];
                        }
                        $label = trim($line);
                        return ['value' => Str::slug($label, '_'), 'label' => $label];
                    })
                    ->filter()
                    ->values();
                $labels = $legacy->pluck('label');
                $values = $legacy->pluck('value');
            }

            if ($labels->count() < 2) {
                throw ValidationException::withMessages([
                    'option_labels' => 'Tambahkan minimal dua pilihan jawaban untuk jenis pertanyaan ini.',
                ]);
            }

            $usedValues = [];
            foreach ($labels as $i => $label) {
                $value = trim((string) ($values[$i] ?? ''));
                $value = $value !== '' ? Str::slug($value, '_') : Str::slug($label, '_');
                $base = $value ?: 'pilihan_' . ($i + 1);
                $value = $base;
                $suffix = 2;
                while (in_array($value, $usedValues, true)) {
                    $value = $base . '_' . $suffix++;
                }
                $usedValues[] = $value;
                $question->options()->create(['value' => $value, 'label' => $label, 'sort_order' => $i + 1]);
            }
        }

        app(AuditService::class)->log('master.question.save', $question, $before, $question->load('options')->toArray());
        return back()->with('success', 'Pertanyaan disimpan.');
    }

    public function rule(Request $request, ?CircularRule $rule = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'priority' => 'required|integer|min:1|max:9999',
            'result_path' => 'required|in:REUSE,DONATION,REPAIR_ASSESSMENT,TECHNICAL_ASSESSMENT,PARTS_RECOVERY,SPECIAL_HANDLING,RECOVERY',
            'condition_field_1' => 'required|string|max:100',
            'condition_value_1' => 'required|string|max:120',
            'condition_field_2' => 'nullable|string|max:100',
            'condition_value_2' => 'nullable|string|max:120',
            'condition_field_3' => 'nullable|string|max:100',
            'condition_value_3' => 'nullable|string|max:120',
            'explanation_template' => 'nullable|string|max:1000',
            'active' => 'nullable|boolean',
        ]);

        $pairs = [
            1 => [trim((string) $data['condition_field_1']), trim((string) $data['condition_value_1'])],
            2 => [trim((string) ($data['condition_field_2'] ?? '')), trim((string) ($data['condition_value_2'] ?? ''))],
            3 => [trim((string) ($data['condition_field_3'] ?? '')), trim((string) ($data['condition_value_3'] ?? ''))],
        ];

        foreach ([2, 3] as $index) {
            [$field, $value] = $pairs[$index];
            if (($field === '') !== ($value === '')) {
                throw ValidationException::withMessages([
                    $field === '' ? "condition_field_{$index}" : "condition_value_{$index}" => "Syarat ke-{$index} harus memilih pertanyaan dan jawabannya sekaligus.",
                ]);
            }
        }

        $usedFields = [];
        foreach ($pairs as $index => [$field, $value]) {
            if ($field === '')
                continue;
            if (in_array($field, $usedFields, true)) {
                throw ValidationException::withMessages([
                    "condition_field_{$index}" => 'Setiap syarat harus menggunakan pertanyaan yang berbeda.',
                ]);
            }
            $usedFields[] = $field;
            $this->assertRuleConditionIsValid($field, $value, "condition_field_{$index}", "condition_value_{$index}");
        }

        $conditions = [];
        foreach ($pairs as [$field, $value]) {
            if ($field !== '')
                $conditions[$field] = $value;
        }

        $before = $rule?->toArray();
        $rule ??= new CircularRule;
        $rule->fill([
            'name' => $data['name'],
            'priority' => $data['priority'],
            'result_path' => $data['result_path'],
            'conditions_json' => $conditions,
            'explanation_template' => $data['explanation_template'] ?? null,
            'active' => $request->boolean('active'),
        ]);
        $rule->save();

        app(AuditService::class)->log('master.rule.save', $rule, $before, $rule->toArray());
        return back()->with('success', 'Aturan rekomendasi disimpan dan akan digunakan pada cek kondisi berikutnya.');
    }

    private function assertRuleConditionIsValid(string $field, string $value, string $fieldKey, string $valueKey): void
    {
        $questions = Question::with('options')
            ->where('code', $field)
            ->whereHas('questionnaireTemplate', fn($q) => $q->where('audience', 'citizen'))
            ->get();

        if ($questions->isEmpty() || $questions->contains(fn($question) => $question->type === 'text')) {
            throw ValidationException::withMessages([
                $fieldKey => 'Pilih pertanyaan dengan jawaban pilihan yang tersedia pada Cek Kondisi Warga.',
            ]);
        }

        $allowedValues = $questions->flatMap->options->pluck('value')->unique()->values();
        if ($allowedValues->isEmpty() || !$allowedValues->contains($value)) {
            throw ValidationException::withMessages([
                $valueKey => 'Jawaban yang dipilih tidak sesuai dengan pertanyaan tersebut. Muat ulang halaman lalu pilih jawaban yang tersedia.',
            ]);
        }
    }
}
