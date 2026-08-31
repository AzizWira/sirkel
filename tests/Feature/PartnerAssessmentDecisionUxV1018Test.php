<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetAssessment, AssetCustody, DeviceCategory, DeviceGroup, HandoverRequest, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\AssetFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerAssessmentDecisionUxV1018Test extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $group = DeviceGroup::create([
            'code' => 'ux18',
            'name' => 'UX 18',
            'sort_order' => 1,
            'active' => true,
        ]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'ux18-device',
            'name' => 'Radio Uji',
            'supports_batch' => false,
            'special_handling_possible' => false,
            'sort_order' => 1,
            'active' => true,
        ]);
        $owner = User::create([
            'name' => 'Warga UX 18',
            'email' => 'owner18@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $partnerUser = User::create([
            'name' => 'Recovery UX 18',
            'email' => 'recovery18@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $profile = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Recovery UX 18',
            'responsible_name' => 'Operator',
            'phone' => '6281218181818',
            'address' => 'Surabaya',
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
            'latitude' => -7.32,
            'longitude' => 112.76,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        $profile->acceptedCategories()->attach($category->id);
        PartnerCapabilityModel::create([
            'partner_profile_id' => $profile->id,
            'capability' => 'recovery',
            'status' => 'approved',
        ]);
        $asset = Asset::create([
            'passport_code' => 'SRK-I-UX18',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'status' => 'received',
            'handover_type' => 'free_handover',
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'verified_weight_kg' => 1.35,
            'core_locked_at' => now(),
        ]);
        AssetCustody::create([
            'asset_id' => $asset->id,
            'partner_profile_id' => $profile->id,
            'received_by_user_id' => $partnerUser->id,
            'received_at' => now(),
        ]);

        return compact('asset', 'partnerUser', 'profile');
    }

    #[Test]
    public function decision_helper_disables_reuse_donation_when_item_is_off_and_severely_damaged(): void
    {
        extract($this->fixture());

        $response = $this->actingAs($partnerUser)
            ->postJson(route('partner.assets.decision-options', $asset), [
                'power_status' => 'off',
                'damage_level' => 'severe',
                'repair_feasible' => 'unknown',
            ])
            ->assertOk();

        $response->assertJsonPath('availability.TRANSFER_REUSE_DONATION.allowed', false);
        $response->assertJsonPath('availability.TRANSFER_REPAIR.allowed', true);
        $this->assertStringContainsString('belum layak disalurkan untuk digunakan kembali', (string) $response->json('guidance'));
    }

    #[Test]
    public function decision_helper_allows_reuse_donation_after_item_is_recorded_usable(): void
    {
        extract($this->fixture());

        $this->actingAs($partnerUser)
            ->postJson(route('partner.assets.decision-options', $asset), [
                'power_status' => 'normal',
                'damage_level' => 'minor',
                'repair_feasible' => 'unknown',
            ])
            ->assertOk()
            ->assertJsonPath('availability.TRANSFER_REUSE_DONATION.allowed', true);
    }

    #[Test]
    public function invalid_web_form_choice_returns_to_form_with_readable_error_instead_of_exception_page(): void
    {
        extract($this->fixture());
        $from = route('partner.assets.show', $asset);

        $this->actingAs($partnerUser)
            ->from($from)
            ->post(route('partner.assets.assess', $asset), [
                'power_status' => 'off',
                'damage_level' => 'severe',
                'repair_feasible' => 'unknown',
                'handling_decision' => 'TRANSFER_REUSE_DONATION',
                'summary' => 'Barang mati dan rusak berat.',
            ])
            ->assertRedirect($from)
            ->assertSessionHasErrors('handling_decision');

        $this->assertDatabaseMissing('asset_assessments', [
            'asset_id' => $asset->id,
            'result_path' => 'TRANSFER_REUSE_DONATION',
        ]);
    }

    #[Test]
    public function assessment_page_exposes_dynamic_decision_guard_to_the_browser(): void
    {
        extract($this->fixture());

        $this->actingAs($partnerUser)
            ->get(route('partner.assets.show', $asset))
            ->assertOk()
            ->assertSee('data-partner-assessment-form', false)
            ->assertSee(route('partner.assets.decision-options', $asset), false)
            ->assertSee('data-decision-code="TRANSFER_REUSE_DONATION"', false);
    }

    #[Test]
    public function recovery_partner_is_not_sent_back_to_repair_when_previous_partner_already_ruled_repair_out(): void
    {
        extract($this->fixture());
        AssetAssessment::create([
            'asset_id' => $asset->id,
            'assessment_type' => 'partner',
            'assessor_user_id' => $partnerUser->id,
            'answers_json' => [
                'power_status' => 'off',
                'damage_level' => 'severe',
                'repair_feasible' => 'no',
            ],
            'result_path' => 'TRANSFER_RECOVERY',
            'summary' => 'Perbaikan sudah dinyatakan tidak layak pada tahap sebelumnya.',
            'verified_weight_kg' => 1.35,
            'verified_at' => now()->subMinute(),
        ]);

        $codes = array_column(app(AssetFlowService::class)->transferOptions($asset->fresh(), $profile->load('capabilities')), 'code');

        $this->assertNotContains('TRANSFER_REPAIR', $codes);
    }

    #[Test]
    public function partner_can_save_an_intermediate_assessment_without_faking_a_final_outcome(): void
    {
        extract($this->fixture());

        $this->actingAs($partnerUser)
            ->post(route('partner.assets.assess', $asset), [
                'power_status' => 'off',
                'damage_level' => 'severe',
                'repair_feasible' => 'unknown',
                'handling_decision' => 'CONTINUE_HANDLING',
                'summary' => 'Barang sudah diperiksa dan proses pemulihan masih berlangsung.',
            ])
            ->assertRedirect(route('partner.assets.show', $asset));

        $this->assertSame('in_processing', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->final_path);
        $this->assertTrue($asset->fresh()->custody()->where('partner_profile_id', $profile->id)->whereNull('released_at')->exists());
        $this->assertDatabaseHas('asset_assessments', [
            'asset_id' => $asset->id,
            'result_path' => 'CONTINUE_HANDLING',
        ]);

        $this->actingAs($partnerUser)
            ->get(route('partner.assets.show', $asset))
            ->assertOk()
            ->assertSee('Catat Pemeriksaan')
            ->assertSee('lanjut proses pemulihan di mitra ini', false);
    }

    #[Test]
    public function deliberate_business_rule_422_from_other_partner_forms_is_rendered_as_form_feedback(): void
    {
        extract($this->fixture());
        $asset->update(['handover_type' => 'donation']);
        $handover = HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $asset->owner_user_id,
            'partner_profile_id' => $profile->id,
            'method' => 'dropoff',
            'handover_type' => 'donation',
            'status' => 'accepted',
        ]);
        $from = route('partner.requests.show', $handover);

        $this->actingAs($partnerUser)
            ->from($from)
            ->post(route('partner.requests.offer', $handover), [
                'amount' => 10000,
                'valid_hours' => 6,
            ])
            ->assertRedirect($from)
            ->assertSessionHasErrors('flow');
    }
}
