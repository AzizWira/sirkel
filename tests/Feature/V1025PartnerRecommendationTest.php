<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\PartnerMatchingService;
use Database\Seeders\{DemoSeeder, MasterDataSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1025PartnerRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private function category(): DeviceCategory
    {
        $group = DeviceGroup::create(['code' => 'v1025', 'name' => 'V1025', 'sort_order' => 1, 'active' => true]);

        return DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'v1025-device',
            'name' => 'Perangkat V1025',
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    private function partner(
        string $email,
        DeviceCategory $category,
        float $lat,
        float $lng,
        string $district,
        array $caps = ['pickup', 'repair']
    ): PartnerProfile {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $partner = PartnerProfile::create([
            'user_id' => $user->id,
            'business_name' => $email,
            'responsible_name' => 'Demo',
            'phone' => '628111111111',
            'address' => 'Surabaya',
            'district' => $district,
            'village' => 'Demo',
            'latitude' => $lat,
            'longitude' => $lng,
            'pickup_radius_km' => 15,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        $partner->acceptedCategories()->attach($category->id);

        foreach ($caps as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $partner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }

        return $partner;
    }

    #[Test]
    public function citizen_matching_marks_one_recommendation_and_keeps_other_eligible_options(): void
    {
        $category = $this->category();
        $owner = User::create([
            'name' => 'Warga',
            'email' => 'warga-v1025@example.test',
            'password' => 'password123',
            'role' => UserRole::USER,
        ]);
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1025',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
        ]);

        $nearest = $this->partner('nearest-v1025@example.test', $category, -7.3350, 112.7850, 'Gunung Anyar');
        $alternative = $this->partner('alternative-v1025@example.test', $category, -7.3500, 112.7900, 'Rungkut');
        $this->partner('wrong-service-v1025@example.test', $category, -7.3341, 112.7860, 'Gunung Anyar', ['pickup', 'recovery']);

        $results = app(PartnerMatchingService::class)->match(
            $asset,
            'pickup',
            -7.3340,
            112.7860,
            'sale',
            'Gunung Anyar'
        );

        $this->assertCount(2, $results);
        $this->assertSame($nearest->id, $results->first()->id);
        $this->assertTrue((bool) $results->first()->is_recommended);
        $this->assertNotEmpty($results->first()->recommendation_reason);
        $this->assertFalse((bool) $results->last()->is_recommended);
        $this->assertSame($alternative->id, $results->last()->id);
    }

    #[Test]
    public function transfer_targets_are_ranked_from_current_partner_location(): void
    {
        $category = $this->category();
        $source = $this->partner('source-v1025@example.test', $category, -7.3340, 112.7860, 'Gunung Anyar', ['repair']);
        $nearRecovery = $this->partner('near-recovery-v1025@example.test', $category, -7.3400, 112.7800, 'Rungkut', ['recovery']);
        $farRecovery = $this->partner('far-recovery-v1025@example.test', $category, -7.2800, 112.7400, 'Gubeng', ['recovery']);

        $ranked = app(PartnerMatchingService::class)->rankTransferTargets(
            collect([$farRecovery, $nearRecovery]),
            $source,
            'recovery'
        );

        $this->assertSame($nearRecovery->id, $ranked->first()->id);
        $this->assertTrue((bool) $ranked->first()->is_recommended);
        $this->assertNotEmpty($ranked->first()->recommendation_reason);
        $this->assertGreaterThan($ranked->first()->transfer_distance_km, $ranked->last()->transfer_distance_km);
    }

    #[Test]
    public function demo_seed_has_overlapping_partner_capabilities_for_matching_demos(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DemoSeeder::class);

        $toaster = DeviceCategory::where('code', 'toaster')->firstOrFail();
        $eligibleRepair = PartnerProfile::query()
            ->where('verification_status', 'approved')
            ->where('admin_status', 'active')
            ->where('accepting_requests', true)
            ->whereHas('acceptedCategories', fn ($q) => $q->where('device_categories.id', $toaster->id))
            ->whereHas('capabilities', fn ($q) => $q->where('capability', 'repair')->where('status', 'approved'))
            ->count();

        $this->assertGreaterThanOrEqual(3, $eligibleRepair);
        $this->assertGreaterThanOrEqual(12, PartnerProfile::count());
        $this->assertGreaterThanOrEqual(3, PartnerCapabilityModel::where('capability', 'recovery')->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(3, PartnerCapabilityModel::where('capability', 'reuse_donation')->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(2, PartnerCapabilityModel::where('capability', 'special_handling')->where('status', 'approved')->count());
    }
}
