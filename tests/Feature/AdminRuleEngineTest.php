<?php

namespace Tests\Feature;

use App\Models\CircularRule;
use App\Services\RuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_database_rule_is_used_by_the_recommendation_engine(): void
    {
        CircularRule::create([
            'name'=>'Uji aturan admin',
            'priority'=>1,
            'active'=>true,
            'conditions_json'=>['power_status'=>'off','damage_level'=>'severe'],
            'result_path'=>'PARTS_RECOVERY',
            'explanation_template'=>'Aturan admin terpakai.',
        ]);

        $result = app(RuleEngine::class)->evaluate(['power_status'=>'off','damage_level'=>'severe','technician_result'=>'not_checked']);

        $this->assertSame('PARTS_RECOVERY',$result['path']);
        $this->assertSame('Aturan admin terpakai.',$result['explanation']);
    }
}
