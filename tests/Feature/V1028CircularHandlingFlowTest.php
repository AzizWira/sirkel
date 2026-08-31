<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\AssetFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1028CircularHandlingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $capabilities): array
    {
        $group = DeviceGroup::create(['code' => 'v1028-flow', 'name' => 'Flow', 'sort_order' => 1, 'active' => true]);
        $category = DeviceCategory::create(['device_group_id' => $group->id, 'code' => 'v1028-device', 'name' => 'Perangkat Uji', 'sort_order' => 1, 'active' => true]);
        $owner = User::create(['name' => 'Warga', 'email' => uniqid('warga'). '@test.local', 'password' => 'password123', 'role' => UserRole::USER]);
        $partnerUser = User::create(['name' => 'Mitra', 'email' => uniqid('mitra'). '@test.local', 'password' => 'password123', 'role' => UserRole::PARTNER]);
        $profile = PartnerProfile::create([
            'user_id' => $partnerUser->id, 'business_name' => 'Mitra Uji', 'responsible_name' => 'Operator', 'phone' => '6281200001028',
            'address' => 'Surabaya', 'district' => 'Rungkut', 'village' => 'Kalirungkut', 'latitude' => -7.32, 'longitude' => 112.77,
            'pickup_radius_km' => 10, 'accepting_requests' => true, 'verification_status' => 'approved', 'admin_status' => 'active',
        ]);
        foreach ($capabilities as $capability) {
            PartnerCapabilityModel::create(['partner_profile_id' => $profile->id, 'capability' => $capability, 'status' => 'approved']);
        }
        $asset = Asset::create([
            'passport_code' => uniqid('SRK-I-'), 'owner_user_id' => $owner->id, 'device_category_id' => $category->id,
            'tracking_type' => 'individual', 'quantity' => 1, 'status' => 'received', 'handover_type' => 'free_handover',
            'preliminary_path' => 'REPAIR_ASSESSMENT', 'core_locked_at' => now(),
        ]);

        return compact('asset', 'profile');
    }

    #[Test]
    public function return_to_owner_is_not_a_new_partner_decision(): void
    {
        extract($this->fixture(['repair']));
        $codes = app(AssetFlowService::class)->allowedDecisionCodes($asset, $profile->load('capabilities'));

        $this->assertNotContains('RETURNED_TO_OWNER', $codes);
        $this->assertContains('REPAIRED', $codes);
    }

    #[Test]
    public function repair_partner_with_recovery_can_finish_component_or_material_recovery_locally(): void
    {
        extract($this->fixture(['repair', 'recovery']));
        $flow = app(AssetFlowService::class);
        $completionCodes = array_column($flow->completionOptions($asset, $profile->load('capabilities')), 'code');
        $transferCodes = array_column($flow->transferOptions($asset, $profile), 'code');

        $this->assertContains('PARTS_RECOVERED', $completionCodes);
        $this->assertContains('RECOVERY_CONFIRMED', $completionCodes);
        $this->assertNotContains('TRANSFER_RECOVERY', $transferCodes);

        $guidance = $flow->assessmentGuidance($asset, $profile, [
            'power_status' => 'off', 'damage_level' => 'severe', 'repair_feasible' => 'no',
            'hazard_found' => 'no', 'recovery_potential' => 'components',
        ]);
        $this->assertStringContainsString('di sini', $guidance);
        $this->assertStringContainsString('transfer tidak diperlukan', strtolower($guidance));
    }

    #[Test]
    public function repair_only_partner_is_directed_to_recovery_when_whole_device_is_not_feasible(): void
    {
        extract($this->fixture(['repair']));
        $flow = app(AssetFlowService::class);
        $codes = array_column($flow->transferOptions($asset, $profile->load('capabilities')), 'code');

        $this->assertContains('TRANSFER_RECOVERY', $codes);
        $this->assertNull($flow->assessmentConflictMessage($asset, $profile, 'TRANSFER_RECOVERY', [
            'power_status' => 'off', 'damage_level' => 'severe', 'repair_feasible' => 'no',
            'hazard_found' => 'no', 'recovery_potential' => 'both',
        ]));
    }
}
