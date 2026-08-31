<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{CircularRule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1029RegressionHotfixTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_master_data_can_render_legacy_array_rule_conditions(): void
    {
        CircularRule::create([
            'name' => 'Legacy array rule untuk regression test',
            'priority' => 999,
            'active' => true,
            'conditions_json' => [
                'power_status' => 'normal',
                'damage_level' => ['none', 'minor'],
                'user_intent' => 'donate',
            ],
            'result_path' => 'DONATION',
            'explanation_template' => 'Legacy rule.',
        ]);

        $admin = User::create([
            'name' => 'Admin V1029',
            'email' => 'admin-v1029@test.local',
            'password' => 'password123',
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '6281210291029',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.master.index'))
            ->assertOk()
            ->assertSee('Legacy array rule untuk regression test')
            ->assertSee('ATAU');
    }

    #[Test]
    public function normalized_donation_rules_do_not_store_array_condition_values(): void
    {
        $rules = CircularRule::query()
            ->whereIn('name', [
                'Donasi perangkat normal sesuai prioritas warga',
                'Donasi perangkat dengan kerusakan ringan sesuai prioritas warga',
            ])
            ->get();

        $this->assertCount(2, $rules);
        foreach ($rules as $rule) {
            $this->assertCount(3, $rule->conditions_json);
            foreach ($rule->conditions_json as $value) {
                $this->assertFalse(is_array($value));
            }
        }
    }
}
