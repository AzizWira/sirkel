<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, HandoverRequest, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\MapLinkService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1028MapAndHandoverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function google_maps_coordinates_can_be_read_without_calling_external_service(): void
    {
        $service = app(MapLinkService::class);

        $point = $service->extractCoordinates('https://www.google.com/maps/place/Rungkut/@-7.3301234,112.7705678,16z');
        $this->assertSame(-7.3301234, $point['latitude']);
        $this->assertSame(112.7705678, $point['longitude']);

        $point = $service->extractCoordinates('https://www.google.com/maps?q=-7.3012345,112.7456789');
        $this->assertSame(-7.3012345, $point['latitude']);
        $this->assertSame(112.7456789, $point['longitude']);

        $this->assertNull($service->resolve('https://example.com/?q=-7.3,112.7'));
    }

    #[Test]
    public function handover_requires_acknowledgement_and_records_it_when_partner_is_selected(): void
    {
        $this->seed(MasterDataSeeder::class);
        $category = DeviceCategory::where('code', 'refrigerator')->firstOrFail();
        $owner = User::create([
            'name' => 'Warga V1028', 'email' => 'warga-v1028-handover@test.local', 'password' => 'password123', 'role' => UserRole::USER,
            'email_verified_at' => now(), 'profile_completed_at' => now(), 'district' => 'Rungkut', 'village' => 'Kalirungkut',
        ]);
        $partnerUser = User::create([
            'name' => 'Mitra V1028', 'email' => 'mitra-v1028-handover@test.local', 'password' => 'password123', 'role' => UserRole::PARTNER,
            'email_verified_at' => now(), 'profile_completed_at' => now(),
        ]);
        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id, 'business_name' => 'Mitra V1028', 'responsible_name' => 'Operator', 'phone' => '6281200010280',
            'address' => 'Rungkut, Surabaya', 'district' => 'Rungkut', 'village' => 'Kalirungkut', 'latitude' => -7.3218, 'longitude' => 112.7715,
            'pickup_radius_km' => 20, 'accepting_requests' => true, 'verification_status' => 'approved', 'admin_status' => 'active',
        ]);
        $partner->acceptedCategories()->attach($category->id);
        foreach (['pickup', 'repair'] as $capability) {
            PartnerCapabilityModel::create(['partner_profile_id' => $partner->id, 'capability' => $capability, 'status' => 'approved']);
        }
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1028-H', 'owner_user_id' => $owner->id, 'device_category_id' => $category->id,
            'tracking_type' => 'individual', 'quantity' => 1, 'status' => 'matching', 'preliminary_path' => 'REPAIR_ASSESSMENT',
            'origin_district' => 'Rungkut', 'origin_village' => 'Kalirungkut',
        ]);

        $payload = [
            'partner_profile_id' => $partner->id, 'method' => 'pickup', 'handover_type' => 'free_handover',
            'latitude' => -7.3218, 'longitude' => 112.7715, 'address' => 'Dekat kantor kelurahan',
            'district' => 'Rungkut', 'village' => 'Kalirungkut', 'requested_date' => now()->addDay()->toDateString(),
            'time_start' => '09:00', 'time_end' => '11:00',
        ];

        $this->actingAs($owner)
            ->from(route('user.handovers.match.form', $asset))
            ->post(route('user.handovers.create', $asset), $payload)
            ->assertSessionHasErrors('ownership_acknowledgement');

        $this->actingAs($owner)
            ->post(route('user.handovers.create', $asset), $payload + ['ownership_acknowledgement' => '1'])
            ->assertRedirect();

        $handover = HandoverRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertNotNull($handover->ownership_acknowledged_at);
    }
}
