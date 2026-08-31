<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset,DeviceCategory,DeviceGroup,HandoverRequest,Offer,PartnerProfile,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerOfferDurationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function partner_can_create_offer_when_valid_hours_arrives_as_a_form_string(): void
    {
        Notification::fake();

        $group = DeviceGroup::create([
            'code' => 'small-household',
            'name' => 'Elektronik Rumah Tangga Kecil',
            'sort_order' => 1,
            'active' => true,
        ]);

        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'blender',
            'name' => 'Blender',
            'sort_order' => 1,
            'active' => true,
        ]);

        $owner = User::create([
            'name' => 'Warga',
            'email' => 'offer-owner@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '628121111111',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar',
        ]);

        $partnerUser = User::create([
            'name' => 'Mitra Repair',
            'email' => 'offer-partner@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Repair',
            'responsible_name' => 'Operator',
            'phone' => '628131111111',
            'address' => 'Surabaya',
            'district' => 'Gunung Anyar',
            'latitude' => -7.334,
            'longitude' => 112.786,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);

        $asset = Asset::create([
            'passport_code' => 'SRK-I-OFFER-TEST',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'status' => 'partner_accepted',
            'handover_type' => 'sale',
        ]);

        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $before = now();

        $this->actingAs($partnerUser)
            ->post(route('partner.requests.offer', $handover), [
                'amount' => '75000',
                'note' => 'Estimasi awal.',
                // Browser form values arrive as strings even with integer validation.
                'valid_hours' => '6',
            ])
            ->assertRedirect();

        $offer = Offer::where('handover_request_id', $handover->id)->firstOrFail();

        $this->assertSame('waiting_user', $offer->status);
        $this->assertSame('75000.00', $offer->amount);
        $this->assertTrue($offer->expires_at->between($before->copy()->addHours(6)->subMinute(), now()->addHours(6)->addMinute()));
        $this->assertSame('offered', $handover->fresh()->status);
    }
}
