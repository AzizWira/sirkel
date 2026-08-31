<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, HandoverRequest, Offer, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\OfferLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlowLifecycleV1016Test extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $capabilities = ['repair']): array
    {
        Notification::fake();

        $group = DeviceGroup::create([
            'code' => 'lifecycle16',
            'name' => 'Lifecycle 16',
            'sort_order' => 1,
            'active' => true,
        ]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'lifecycle16-device',
            'name' => 'Perangkat Lifecycle',
            'supports_batch' => true,
            'special_handling_possible' => false,
            'sort_order' => 1,
            'active' => true,
        ]);
        $owner = User::create([
            'name' => 'Warga Lifecycle',
            'email' => 'lifecycle-owner@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '6281212345678',
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
        ]);
        $partnerUser = User::create([
            'name' => 'Operator Lifecycle',
            'email' => 'lifecycle-partner@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Lifecycle',
            'responsible_name' => 'Operator',
            'phone' => '6281312345678',
            'address' => 'Jl. Mitra Surabaya',
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
            'latitude' => -7.3200000,
            'longitude' => 112.7600000,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        $partner->acceptedCategories()->attach($category->id);
        foreach ($capabilities as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $partner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }

        return compact('group', 'category', 'owner', 'partnerUser', 'partner');
    }

    private function asset(User $owner, DeviceCategory $category, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'passport_code' => 'SRK-I-LIFE16-'.strtoupper(str()->random(5)),
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'status' => 'partner_accepted',
            'handover_type' => 'sale',
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'origin_district' => 'Rungkut',
            'origin_village' => 'Kalirungkut',
        ], $overrides));
    }

    #[Test]
    public function expired_offer_returns_request_to_accepted_without_closing_the_handover(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, ['status' => 'offered']);
        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'status' => 'offered',
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time_start' => '09:00',
            'requested_time_end' => '11:00',
            'schedule_status' => 'requested',
        ]);
        $offer = Offer::create([
            'handover_request_id' => $handover->id,
            'version' => 1,
            'amount' => 50000,
            'offered_at' => now()->subHours(2),
            'expires_at' => now()->subMinute(),
            'is_current' => true,
            'status' => 'waiting_user',
        ]);

        $this->assertSame(1, app(OfferLifecycleService::class)->expireOverdue($handover->id));
        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertFalse($offer->fresh()->is_current);
        $this->assertSame('accepted', $handover->fresh()->status);
        $this->assertSame('partner_accepted', $asset->fresh()->status);
        $this->assertNotContains($handover->fresh()->status, HandoverRequest::TERMINAL_STATUSES);

        $this->actingAs($partnerUser)
            ->post(route('partner.requests.offer', $handover), [
                'amount' => 60000,
                'valid_hours' => 6,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('offers', [
            'handover_request_id' => $handover->id,
            'version' => 2,
            'status' => 'waiting_user',
            'is_current' => true,
        ]);
    }

    #[Test]
    public function sale_must_finish_offer_and_schedule_before_receive(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category);
        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'status' => 'accepted',
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time_start' => '09:00',
            'requested_time_end' => '11:00',
            'schedule_status' => 'requested',
            'accepted_at' => now(),
        ]);

        $this->actingAs($partnerUser)
            ->postJson(route('partner.requests.propose-time', $handover), [
                'proposed_time' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422);

        $handover->update([
            'status' => 'offer_accepted',
            'schedule_status' => 'proposed',
            'partner_proposed_time' => now()->addDays(2)->setTime(10, 0),
        ]);
        $asset->update(['status' => 'scheduled']);

        $this->actingAs($partnerUser)
            ->postJson(route('partner.requests.receive', $handover), ['verified_weight_kg' => 1.2])
            ->assertStatus(422);

        $this->actingAs($owner)
            ->post(route('user.handovers.schedule.accept', $handover))
            ->assertRedirect();

        $this->actingAs($partnerUser)
            ->post(route('partner.requests.receive', $handover), ['verified_weight_kg' => 1.2])
            ->assertRedirect(route('partner.assets.show', $asset));

        $this->assertNotNull($asset->fresh()->core_locked_at);
        $this->assertSame('completed', $handover->fresh()->status);
        $this->assertSame('received', $asset->fresh()->status);
    }

    #[Test]
    public function handover_type_is_a_request_snapshot_not_the_assets_latest_value(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, ['handover_type' => 'donation']);
        $saleRequest = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'dropoff',
            'handover_type' => 'sale',
            'status' => 'accepted',
            'requested_date' => now()->addDay()->toDateString(),
            'schedule_status' => 'requested',
            'accepted_at' => now(),
        ]);

        $this->assertSame('sale', $saleRequest->effectiveHandoverType());
        $this->actingAs($partnerUser)
            ->post(route('partner.requests.offer', $saleRequest), [
                'amount' => 25000,
                'valid_hours' => 6,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('offers', ['handover_request_id' => $saleRequest->id, 'version' => 1]);

        $donationRequest = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'dropoff',
            'handover_type' => 'donation',
            'status' => 'accepted',
            'requested_date' => now()->addDay()->toDateString(),
            'schedule_status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $asset->update(['handover_type' => 'sale']);

        $this->assertSame('donation', $donationRequest->effectiveHandoverType());
        $this->actingAs($partnerUser)
            ->postJson(route('partner.requests.offer', $donationRequest), [
                'amount' => 25000,
                'valid_hours' => 6,
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function dropoff_does_not_expose_home_location_to_partner_but_pickup_does(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, ['handover_type' => 'free_handover']);

        $dropoff = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'dropoff',
            'handover_type' => 'free_handover',
            'status' => 'accepted',
            'pickup_latitude' => -7.3333333,
            'pickup_longitude' => 112.7777777,
            'pickup_address' => 'ALAMAT RAHASIA WARGA',
            'pickup_district' => 'Rungkut',
            'pickup_village' => 'Kalirungkut',
            'distance_km' => 2.1,
            'requested_date' => now()->addDay()->toDateString(),
            'schedule_status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->actingAs($partnerUser)
            ->get(route('partner.requests.show', $dropoff))
            ->assertOk()
            ->assertSee('Warga akan mengantar barang ke lokasi mitra')
            ->assertDontSee('ALAMAT RAHASIA WARGA')
            ->assertDontSee('-7.3333333')
            ->assertDontSee('112.7777777');

        $pickup = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'free_handover',
            'status' => 'accepted',
            'pickup_latitude' => -7.3333333,
            'pickup_longitude' => 112.7777777,
            'pickup_address' => 'ALAMAT PICKUP TERLIHAT',
            'pickup_district' => 'Rungkut',
            'pickup_village' => 'Kalirungkut',
            'distance_km' => 2.1,
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time_start' => '09:00',
            'requested_time_end' => '11:00',
            'schedule_status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->actingAs($partnerUser)
            ->get(route('partner.requests.show', $pickup))
            ->assertOk()
            ->assertSee('ALAMAT PICKUP TERLIHAT')
            ->assertSee('-7.3333333');
    }

    #[Test]
    public function dropoff_matching_does_not_require_home_address_while_pickup_does(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, [
            'status' => 'assessed',
            'handover_type' => null,
            'core_locked_at' => null,
        ]);

        $base = [
            'handover_type' => 'free_handover',
            'latitude' => -7.3200000,
            'longitude' => 112.7600000,
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
            'requested_date' => now()->addDay()->toDateString(),
        ];

        $this->actingAs($owner)
            ->post(route('user.handovers.match', $asset), $base + ['method' => 'dropoff'])
            ->assertRedirect(route('user.handovers.partners', $asset));

        $this->actingAs($owner)
            ->get(route('user.handovers.partners', $asset))
            ->assertOk();

        $this->actingAs($owner)
            ->from(route('user.handovers.match.form', $asset))
            ->post(route('user.handovers.match', $asset), $base + [
                'method' => 'pickup',
                'time_start' => '09:00',
                'time_end' => '11:00',
            ])
            ->assertSessionHasErrors('address');
    }

    #[Test]
    public function rejected_offer_can_request_a_new_offer_from_the_same_partner_without_losing_handover_data(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, ['status' => 'offered']);
        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'status' => 'offered',
            'pickup_latitude' => -7.321,
            'pickup_longitude' => 112.761,
            'pickup_address' => 'Patokan rumah demo',
            'pickup_district' => 'Rungkut',
            'pickup_village' => 'Kalirungkut',
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time_start' => '09:00',
            'requested_time_end' => '11:00',
            'schedule_status' => 'requested',
            'accepted_at' => now(),
        ]);
        $offer = Offer::create([
            'handover_request_id' => $handover->id,
            'version' => 1,
            'amount' => 50000,
            'offered_at' => now(),
            'expires_at' => now()->addHours(6),
            'is_current' => true,
            'status' => 'waiting_user',
        ]);

        $this->actingAs($owner)->post(route('user.offers.respond', $offer), [
            'decision' => 'reject',
            'rejection_reason' => 'Nilai belum sesuai',
        ])->assertRedirect(route('user.assets.show', $asset));

        $this->assertSame('offer_rejected', $handover->fresh()->status);
        $this->assertSame('offer_rejected', $asset->fresh()->status);

        $this->actingAs($owner)->post(route('user.handovers.offer-rejected.next', $handover), [
            'action' => 'reoffer',
        ])->assertRedirect(route('user.assets.show', $asset));

        $handover->refresh();
        $this->assertSame('accepted', $handover->status);
        $this->assertSame('partner_accepted', $asset->fresh()->status);
        $this->assertSame('pickup', $handover->method);
        $this->assertSame('Patokan rumah demo', $handover->pickup_address);
        $this->assertSame('09:00', substr((string) $handover->requested_time_start, 0, 5));

        $this->actingAs($partnerUser)->post(route('partner.requests.offer', $handover), [
            'amount' => 60000,
            'valid_hours' => 6,
        ])->assertRedirect();

        $this->assertDatabaseHas('offers', [
            'handover_request_id' => $handover->id,
            'version' => 2,
            'status' => 'waiting_user',
            'is_current' => true,
        ]);
    }

    #[Test]
    public function changing_partner_after_offer_rejection_preserves_previous_handover_form_context(): void
    {
        extract($this->fixture());
        $asset = $this->asset($owner, $category, ['status' => 'offer_rejected']);
        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'status' => 'offer_rejected',
            'pickup_latitude' => -7.321,
            'pickup_longitude' => 112.761,
            'pickup_address' => 'Alamat tidak perlu diketik ulang',
            'pickup_district' => 'Rungkut',
            'pickup_village' => 'Kalirungkut',
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time_start' => '10:00',
            'requested_time_end' => '12:00',
            'schedule_status' => 'requested',
        ]);

        $response = $this->actingAs($owner)->post(route('user.handovers.offer-rejected.next', $handover), [
            'action' => 'change_partner',
        ]);

        $response->assertRedirect(route('user.handovers.partners', $asset));
        $response->assertSessionHas('handover_match.'.$asset->id.'.method', 'pickup');
        $response->assertSessionHas('handover_match.'.$asset->id.'.handover_type', 'sale');
        $response->assertSessionHas('handover_match.'.$asset->id.'.address', 'Alamat tidak perlu diketik ulang');
        $response->assertSessionHas('handover_match.'.$asset->id.'.time_start', '10:00');
        $this->assertSame('matching', $asset->fresh()->status);
        $this->assertSame('offer_rejected', $handover->fresh()->status);
    }

}
