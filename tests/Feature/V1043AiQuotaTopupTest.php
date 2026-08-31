<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AiTopupRequest, AiUsageLog, SystemSetting, User};
use App\Services\AiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1043AiQuotaTopupTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'quota-user@example.test'): User
    {
        return User::create([
            'name' => 'Warga Kuota',
            'email' => $email,
            'password' => 'password123',
            'role' => UserRole::USER,
            'whatsapp' => '6281212345678',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Kuota',
            'email' => 'quota-admin@example.test',
            'password' => 'password123',
            'role' => UserRole::ADMIN,
            'whatsapp' => '628111111111',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function free_quota_counts_only_successful_provider_usage(): void
    {
        $user = $this->user();
        $quota = app(AiQuotaService::class);

        $this->assertSame(5, $quota->status($user, AiQuotaService::ASSET_INTAKE)['remaining']);
        $this->assertSame(20, $quota->status($user, AiQuotaService::CONDITION_DESCRIPTION)['remaining']);

        AiUsageLog::create([
            'feature' => AiQuotaService::ASSET_INTAKE,
            'user_id' => $user->id,
            'model' => 'gpt-test',
            'input_tokens' => 10,
            'cached_input_tokens' => 0,
            'output_tokens' => 5,
            'estimated_cost_usd' => 0.001,
            'latency_ms' => 100,
            'status' => 'success',
            'request_hash' => 'success-1',
        ]);
        AiUsageLog::create([
            'feature' => AiQuotaService::ASSET_INTAKE,
            'user_id' => $user->id,
            'model' => 'gpt-test',
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => 0,
            'latency_ms' => 100,
            'status' => 'failed',
            'request_hash' => 'failed-1',
        ]);

        $status = $quota->status($user, AiQuotaService::ASSET_INTAKE);
        $this->assertSame(1, $status['used']);
        $this->assertSame(4, $status['remaining']);
    }

    #[Test]
    public function only_approved_topup_adds_to_available_quota(): void
    {
        $user = $this->user();
        $quota = app(AiQuotaService::class);

        AiTopupRequest::create([
            'user_id' => $user->id,
            'status' => AiTopupRequest::STATUS_PENDING,
            'asset_intake_quantity' => 9,
            'condition_description_quantity' => 11,
            'asset_intake_unit_price_idr' => 2000,
            'condition_description_unit_price_idr' => 500,
            'total_amount_idr' => 23500,
            'requested_at' => now(),
        ]);

        $this->assertSame(5, $quota->status($user, AiQuotaService::ASSET_INTAKE)['remaining']);

        AiTopupRequest::create([
            'user_id' => $user->id,
            'status' => AiTopupRequest::STATUS_APPROVED,
            'asset_intake_quantity' => 10,
            'condition_description_quantity' => 20,
            'asset_intake_unit_price_idr' => 2000,
            'condition_description_unit_price_idr' => 500,
            'total_amount_idr' => 30000,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->assertSame(15, $quota->status($user, AiQuotaService::ASSET_INTAKE)['remaining']);
        $this->assertSame(40, $quota->status($user, AiQuotaService::CONDITION_DESCRIPTION)['remaining']);
    }

    #[Test]
    public function user_topup_creates_immutable_snapshot_and_whatsapp_message_with_opaque_review_link(): void
    {
        $user = $this->user();
        $admin = $this->admin();
        SystemSetting::updateOrCreate(['key' => 'ai.topup_admin_whatsapp'], ['value' => $admin->whatsapp, 'type' => 'string', 'group' => 'ai']);
        SystemSetting::updateOrCreate(['key' => 'ai.quota.asset_intake_price_idr'], ['value' => '2000', 'type' => 'integer', 'group' => 'ai']);
        SystemSetting::updateOrCreate(['key' => 'ai.quota.condition_description_price_idr'], ['value' => '500', 'type' => 'integer', 'group' => 'ai']);

        $response = $this->actingAs($user)->post(route('user.ai-quota.store'), [
            'asset_intake_quantity' => 3,
            'condition_description_quantity' => 4,
        ]);

        $topup = AiTopupRequest::query()->firstOrFail();
        $this->assertSame(8000, $topup->total_amount_idr);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $topup->public_id);
        $this->assertStringContainsString('Nama: Warga Kuota', (string) $topup->whatsapp_message);
        $this->assertStringContainsString('Email: quota-user@example.test', (string) $topup->whatsapp_message);
        $this->assertStringContainsString('DILARANG mengubah', (string) $topup->whatsapp_message);
        $this->assertStringContainsString('/r/'.$topup->public_id, (string) $topup->whatsapp_message);
        $this->assertStringNotContainsString('/admin/', (string) $topup->whatsapp_message);
        $response->assertRedirect();
        $this->assertStringContainsString('https://wa.me/'.$admin->whatsapp, (string) $response->headers->get('Location'));
    }

    #[Test]
    public function opaque_whatsapp_link_only_resolves_for_admin_and_approval_activates_quota(): void
    {
        $user = $this->user();
        $admin = $this->admin();
        $topup = AiTopupRequest::create([
            'user_id' => $user->id,
            'status' => AiTopupRequest::STATUS_PENDING,
            'asset_intake_quantity' => 7,
            'condition_description_quantity' => 13,
            'asset_intake_unit_price_idr' => 2000,
            'condition_description_unit_price_idr' => 500,
            'total_amount_idr' => 20500,
            'requested_at' => now(),
        ]);

        $this->actingAs($user)->get(route('ai-topups.resolve', $topup->public_id))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('ai-topups.resolve', $topup->public_id))
            ->assertRedirect(route('admin.ai-quota.show', $topup));

        $this->actingAs($admin)
            ->post(route('admin.ai-quota.approve', $topup))
            ->assertRedirect(route('admin.ai-quota.show', $topup));

        $topup->refresh();
        $this->assertSame(AiTopupRequest::STATUS_APPROVED, $topup->status);
        $this->assertSame($admin->id, $topup->reviewed_by);
        $this->assertSame(12, app(AiQuotaService::class)->status($user, AiQuotaService::ASSET_INTAKE)['remaining']);
        $this->assertSame(33, app(AiQuotaService::class)->status($user, AiQuotaService::CONDITION_DESCRIPTION)['remaining']);
    }

    #[Test]
    public function settings_control_free_quota_and_unit_prices(): void
    {
        $user = $this->user();
        SystemSetting::updateOrCreate(['key' => 'ai.quota.asset_intake_free'], ['value' => '8', 'type' => 'integer', 'group' => 'ai']);
        SystemSetting::updateOrCreate(['key' => 'ai.quota.condition_description_free'], ['value' => '25', 'type' => 'integer', 'group' => 'ai']);
        SystemSetting::updateOrCreate(['key' => 'ai.quota.asset_intake_price_idr'], ['value' => '2500', 'type' => 'integer', 'group' => 'ai']);
        SystemSetting::updateOrCreate(['key' => 'ai.quota.condition_description_price_idr'], ['value' => '750', 'type' => 'integer', 'group' => 'ai']);

        $all = app(AiQuotaService::class)->all($user);
        $this->assertSame(8, $all[AiQuotaService::ASSET_INTAKE]['remaining']);
        $this->assertSame(2500, $all[AiQuotaService::ASSET_INTAKE]['unit_price_idr']);
        $this->assertSame(25, $all[AiQuotaService::CONDITION_DESCRIPTION]['remaining']);
        $this->assertSame(750, $all[AiQuotaService::CONDITION_DESCRIPTION]['unit_price_idr']);
    }
}
