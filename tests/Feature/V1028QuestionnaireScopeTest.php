<?php

namespace Tests\Feature;

use App\Models\{Asset, DeviceCategory, QuestionnaireTemplate, User};
use App\Services\QuestionnaireService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1028QuestionnaireScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function partner_questionnaire_merges_global_group_and_category_layers_without_cloning_base_questions(): void
    {
        $this->seed(MasterDataSeeder::class);

        $category = DeviceCategory::where('code', 'refrigerator')->firstOrFail();
        $owner = User::create(['name' => 'Warga Q', 'email' => 'warga-v1028-q@test.local', 'password' => 'password123']);
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1028-Q',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'status' => 'received',
            'preliminary_path' => 'REPAIR_ASSESSMENT',
        ]);

        $template = app(QuestionnaireService::class)->forAsset($asset, 'partner');
        $this->assertNotNull($template);
        $this->assertSame('partner-refrigerator-assessment', $template->code);

        $codes = $template->questions->pluck('code')->all();
        foreach (['power_status', 'damage_level', 'repair_feasible', 'hazard_found', 'recovery_potential', 'major_system_condition', 'cooling_system_status', 'refrigerant_risk'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(count($codes), count(array_unique($codes)));

        $group = QuestionnaireTemplate::where('code', 'partner-large-household-assessment')->firstOrFail();
        $this->assertSame(['major_system_condition'], $group->questions()->pluck('code')->all());

        $categoryTemplate = QuestionnaireTemplate::where('code', 'partner-refrigerator-assessment')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['cooling_system_status', 'refrigerant_risk'],
            $categoryTemplate->questions()->pluck('code')->all()
        );
    }
}
