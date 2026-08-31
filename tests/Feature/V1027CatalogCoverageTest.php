<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, PartnerCapabilityModel, PartnerProfile, QuestionnaireTemplate, User};
use App\Services\{PartnerMatchingService, RuleEngine};
use Database\Seeders\{DemoSeeder, MasterDataSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1027CatalogCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_catalog_covers_major_electronic_groups_and_keeps_fallback_entries(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertGreaterThanOrEqual(80, DeviceCategory::where('active', true)->count());

        foreach ([
            'refrigerator', 'washing-machine', 'air-conditioner', 'television', 'air-fryer',
            'game-console', 'blood-pressure-monitor', 'electric-drill', 'external-storage',
            'other-large-household', 'other-audio-video', 'uncategorized-electronics',
        ] as $code) {
            $this->assertNotNull(DeviceCategory::where('code', $code)->where('active', true)->first(), "Kategori {$code} belum tersedia.");
        }

        $this->assertTrue(DeviceCategory::where('code', 'other-large-household')->firstOrFail()->requiresCustomName());
        $this->assertTrue(DeviceCategory::where('code', 'uncategorized-electronics')->firstOrFail()->requiresCustomName());
        $this->assertFalse(DeviceCategory::where('code', 'refrigerator')->firstOrFail()->requiresCustomName());
    }

    #[Test]
    public function questionnaire_safety_and_data_rules_follow_the_expanded_catalog(): void
    {
        $this->seed(MasterDataSeeder::class);

        $generic = QuestionnaireTemplate::where('code', 'generic-small-electronics')->firstOrFail();
        $this->assertNotNull($generic->questions()->where('code', 'hazard_sign')->first());

        $refrigerator = DeviceCategory::where('code', 'refrigerator')->firstOrFail();
        $refrigeratorTemplate = QuestionnaireTemplate::where('device_category_id', $refrigerator->id)->firstOrFail();
        $this->assertNotNull($refrigeratorTemplate->questions()->where('code', 'cooling_leak')->first());

        $storage = DeviceCategory::where('code', 'external-storage')->firstOrFail();
        $storageTemplate = QuestionnaireTemplate::where('device_category_id', $storage->id)->firstOrFail();
        $this->assertNotNull($storageTemplate->questions()->where('code', 'personal_data')->first());

        $smartphone = DeviceCategory::where('code', 'smartphone')->firstOrFail();
        $smartphoneTemplate = QuestionnaireTemplate::where('device_category_id', $smartphone->id)->firstOrFail();
        $this->assertNotNull($smartphoneTemplate->questions()->where('code', 'battery_swollen')->first());
        $this->assertNotNull($smartphoneTemplate->questions()->where('code', 'personal_data')->first());
    }

    #[Test]
    public function partner_accepting_group_fallback_can_receive_a_specific_device_in_that_group(): void
    {
        $this->seed(MasterDataSeeder::class);

        $owner = User::create([
            'name' => 'Warga V1027',
            'email' => 'warga-v1027@example.test',
            'password' => 'password123',
            'role' => UserRole::USER,
        ]);
        $partnerUser = User::create([
            'name' => 'Mitra Umum Rumah Tangga',
            'email' => 'mitra-v1027@example.test',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
        ]);
        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Umum Rumah Tangga',
            'responsible_name' => 'Operator',
            'phone' => '6281200000027',
            'address' => 'Surabaya',
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
            'latitude' => -7.3218,
            'longitude' => 112.7715,
            'pickup_radius_km' => 15,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        foreach (['pickup', 'repair'] as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $partner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }
        $partner->acceptedCategories()->attach(DeviceCategory::where('code', 'other-large-household')->firstOrFail()->id);

        $exactUser = User::create([
            'name' => 'Spesialis Kulkas',
            'email' => 'spesialis-v1027@example.test',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
        ]);
        $exactPartner = PartnerProfile::create([
            'user_id' => $exactUser->id,
            'business_name' => 'Spesialis Kulkas',
            'responsible_name' => 'Operator',
            'phone' => '6281200000028',
            'address' => 'Surabaya',
            'district' => 'Wonokromo',
            'village' => 'Darmo',
            'latitude' => -7.3500,
            'longitude' => 112.7400,
            'pickup_radius_km' => 20,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        foreach (['pickup', 'repair'] as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $exactPartner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }
        $exactPartner->acceptedCategories()->attach(DeviceCategory::where('code', 'refrigerator')->firstOrFail()->id);

        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1027',
            'owner_user_id' => $owner->id,
            'device_category_id' => DeviceCategory::where('code', 'refrigerator')->firstOrFail()->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'origin_district' => 'Rungkut',
            'origin_village' => 'Kalirungkut',
        ]);

        $results = app(PartnerMatchingService::class)->match($asset, 'pickup', -7.3220, 112.7720, 'free_handover', 'Rungkut');

        $this->assertCount(2, $results);
        $this->assertSame($exactPartner->id, $results->first()->id);
        $this->assertSame('exact', $results->first()->category_match_type);
        $this->assertSame($partner->id, $results->last()->id);
        $this->assertSame('group', $results->last()->category_match_type);
        $this->assertTrue(app(PartnerMatchingService::class)->supportsCategory($partner, $asset));
    }

    #[Test]
    public function hazardous_cooling_and_standalone_battery_routes_stay_synchronized(): void
    {
        $this->seed(MasterDataSeeder::class);
        $engine = app(RuleEngine::class);

        $refrigerator = DeviceCategory::where('code', 'refrigerator')->firstOrFail();
        $this->assertSame('SPECIAL_HANDLING', $engine->evaluate([
            'power_status' => 'off',
            'damage_level' => 'moderate',
            'cooling_leak' => 'yes',
            'user_intent' => 'safe_handover',
        ], ['device_category_id' => $refrigerator->id])['path']);

        $battery = DeviceCategory::where('code', 'battery')->firstOrFail();
        $this->assertSame('RECOVERY', $engine->evaluate([
            'power_status' => 'normal',
            'damage_level' => 'none',
            'battery_swollen' => 'no',
            'battery_leaking' => 'no',
            'user_intent' => 'safe_handover',
        ], ['device_category_id' => $battery->id])['path']);
    }

    #[Test]
    public function demo_seed_provides_multiple_repair_options_for_large_and_unknown_electronics(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DemoSeeder::class);

        foreach (['refrigerator', 'uncategorized-electronics'] as $code) {
            $category = DeviceCategory::where('code', $code)->firstOrFail();
            $count = PartnerProfile::query()
                ->where('verification_status', 'approved')
                ->where('admin_status', 'active')
                ->where('accepting_requests', true)
                ->whereHas('acceptedCategories', fn ($q) => $q->where('device_categories.id', $category->id))
                ->whereHas('capabilities', fn ($q) => $q->where('capability', 'repair')->where('status', 'approved'))
                ->count();

            $this->assertGreaterThanOrEqual(3, $count, "Pilihan repair demo untuk {$code} masih terlalu sedikit.");
        }

        $this->assertGreaterThanOrEqual(15, PartnerProfile::count());
        $this->assertGreaterThanOrEqual(3, PartnerCapabilityModel::where('capability', 'special_handling')->where('status', 'approved')->count());
    }
}
