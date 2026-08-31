<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRuleValidationV1016Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Rule',
            'email' => 'admin-rule16@test.local',
            'password' => 'password123',
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aturan uji validasi',
            'priority' => 99,
            'result_path' => 'REPAIR_ASSESSMENT',
            'condition_field_1' => 'power_status',
            'condition_value_1' => 'off',
            'condition_field_2' => '',
            'condition_value_2' => '',
            'active' => '1',
        ], $overrides);
    }

    #[Test]
    public function rule_builder_rejects_free_text_question_and_mismatched_option(): void
    {
        $this->seed(MasterDataSeeder::class);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.master.rule'), $this->payload([
                'condition_field_1' => 'notes',
                'condition_value_1' => 'apa_saja',
            ]))
            ->assertSessionHasErrors('condition_field_1');

        $this->actingAs($admin)
            ->post(route('admin.master.rule'), $this->payload([
                'condition_field_1' => 'power_status',
                'condition_value_1' => 'severe', // severe milik damage_level, bukan power_status
            ]))
            ->assertSessionHasErrors('condition_value_1');

        $this->actingAs($admin)
            ->post(route('admin.master.rule'), $this->payload([
                'condition_field_2' => 'power_status',
                'condition_value_2' => 'normal',
            ]))
            ->assertSessionHasErrors('condition_field_2');
    }

    #[Test]
    public function valid_structured_rule_is_saved(): void
    {
        $this->seed(MasterDataSeeder::class);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.master.rule'), $this->payload([
                'condition_field_2' => 'damage_level',
                'condition_value_2' => 'severe',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('circular_rules', [
            'name' => 'Aturan uji validasi',
            'result_path' => 'REPAIR_ASSESSMENT',
            'active' => 1,
        ]);
    }
}
