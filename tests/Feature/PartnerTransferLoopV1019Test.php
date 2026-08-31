<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetAssessment, AssetCustody, DeviceCategory, DeviceGroup, HandoverRequest, PartnerCapabilityModel, PartnerProfile, PartnerTransfer, User};
use App\Services\AssetFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerTransferLoopV1019Test extends TestCase
{
    use RefreshDatabase;

    private function partner(DeviceCategory $category, string $email, string $name, array $capabilities): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $profile = PartnerProfile::create([
            'user_id' => $user->id,
            'business_name' => $name,
            'responsible_name' => 'Operator',
            'phone' => '6281219191919',
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
        foreach ($capabilities as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $profile->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }

        return [$user, $profile];
    }

    private function scenario(bool $withReverseTransfer = false): array
    {
        $group = DeviceGroup::create(['code'=>'flow19','name'=>'Elektronik Kecil','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create([
            'device_group_id'=>$group->id,
            'code'=>'radio-flow19',
            'name'=>'Radio',
            'sort_order'=>1,
            'active'=>true,
        ]);
        $owner = User::create([
            'name'=>'Warga',
            'email'=>'owner19@test.local',
            'password'=>'password123',
            'role'=>UserRole::USER,
            'email_verified_at'=>now(),
            'profile_completed_at'=>now(),
        ]);
        [$repairUser, $repair] = $this->partner($category, 'repair19@test.local', 'Sirkular Service Gunung Anyar', ['repair']);
        [$recoveryUser, $recovery] = $this->partner($category, 'recovery19@test.local', 'Mitra Recovery Surabaya', ['recovery']);

        $asset = Asset::create([
            'passport_code'=>'SRK-I-FLOW19',
            'owner_user_id'=>$owner->id,
            'device_category_id'=>$category->id,
            'custom_item_name'=>'Radio',
            'tracking_type'=>'individual',
            'quantity'=>1,
            'status'=>$withReverseTransfer ? 'transfer_pending' : 'received',
            'handover_type'=>'free_handover',
            'preliminary_path'=>'REPAIR_ASSESSMENT',
            'verified_weight_kg'=>1.35,
            'core_locked_at'=>now(),
        ]);

        $handover = HandoverRequest::create([
            'asset_id'=>$asset->id,
            'user_id'=>$owner->id,
            'partner_profile_id'=>$repair->id,
            'method'=>'pickup',
            'handover_type'=>'free_handover',
            'status'=>'completed',
            'distance_km'=>3,
        ]);

        AssetCustody::create([
            'asset_id'=>$asset->id,
            'partner_profile_id'=>$repair->id,
            'received_by_user_id'=>$repairUser->id,
            'received_at'=>now()->subHours(2),
            'released_at'=>now()->subHour(),
        ]);
        AssetCustody::create([
            'asset_id'=>$asset->id,
            'partner_profile_id'=>$recovery->id,
            'received_by_user_id'=>$recoveryUser->id,
            'received_at'=>now()->subHour(),
        ]);

        AssetAssessment::create([
            'asset_id'=>$asset->id,
            'assessment_type'=>'partner',
            'assessor_user_id'=>$repairUser->id,
            'answers_json'=>['power_status'=>'off','damage_level'=>'severe','repair_feasible'=>'no'],
            'result_path'=>'TRANSFER_RECOVERY',
            'summary'=>'Tidak layak diperbaiki. Lanjut ke pemulihan material.',
            'verified_weight_kg'=>1.35,
            'verified_at'=>now()->subHours(2),
        ]);

        $reverseTransfer = null;
        if ($withReverseTransfer) {
            AssetAssessment::create([
                'asset_id'=>$asset->id,
                'assessment_type'=>'partner',
                'assessor_user_id'=>$recoveryUser->id,
                'answers_json'=>['power_status'=>'off','damage_level'=>'severe','repair_feasible'=>'unknown'],
                'result_path'=>'TRANSFER_REPAIR',
                'summary'=>'Legacy state yang mencoba mengirim kembali ke Repair.',
                'verified_weight_kg'=>1.35,
                'verified_at'=>now(),
            ]);
            $reverseTransfer = PartnerTransfer::create([
                'asset_id'=>$asset->id,
                'from_partner_id'=>$recovery->id,
                'to_partner_id'=>$repair->id,
                'requested_by_user_id'=>$recoveryUser->id,
                'required_capability'=>'repair',
                'status'=>'pending',
                'note'=>'Legacy reverse transfer.',
                'requested_at'=>now(),
            ]);
        }

        return compact('asset','handover','repairUser','repair','recoveryUser','recovery','reverseTransfer');
    }

    #[Test]
    public function previous_repair_rejection_is_respected_even_when_it_was_recorded_by_another_partner(): void
    {
        extract($this->scenario());

        $asset->load('assessments');
        $codes = array_column(
            app(AssetFlowService::class)->transferOptions($asset, $recovery->load('capabilities')),
            'code'
        );

        $this->assertNotContains('TRANSFER_REPAIR', $codes);
        $this->assertNotNull(app(AssetFlowService::class)->transferCapabilityConflict($asset, 'repair'));
    }

    #[Test]
    public function historical_partner_detail_never_exposes_operational_transfer_action_after_custody_moved(): void
    {
        extract($this->scenario());

        $this->actingAs($repairUser)
            ->get(route('partner.assets.show', $asset))
            ->assertOk()
            ->assertSee('Riwayat Penanganan')
            ->assertSee('Barang sudah tidak berada dalam tanggung jawab mitra Anda')
            ->assertSee('Mitra Recovery Surabaya')
            ->assertDontSee('Pilih Mitra Lanjutan');
    }

    #[Test]
    public function completed_citizen_handover_is_not_shown_as_active_work_on_partner_dashboard(): void
    {
        extract($this->scenario());

        $this->actingAs($repairUser)
            ->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee('Permintaan Warga')
            ->assertDontSee('SRK-I-FLOW19');
    }

    #[Test]
    public function legacy_reverse_transfer_is_presented_only_as_a_new_incoming_transfer_not_as_old_history_action(): void
    {
        extract($this->scenario(true));

        $response = $this->actingAs($repairUser)
            ->get(route('partner.assets.show', $asset))
            ->assertOk()
            ->assertDontSee('Pilih Mitra Lanjutan');

        $visibleText = preg_replace('/\s+/u', ' ', strip_tags($response->getContent()));
        $this->assertStringContainsString('Ada pengalihan baru yang meminta respons mitra Anda', $visibleText);
        $this->assertStringContainsString('Tinjau Pengalihan Baru', $visibleText);
    }

    #[Test]
    public function upgrade_cleanup_cancels_an_existing_direct_reverse_transfer_and_returns_asset_to_current_handler(): void
    {
        extract($this->scenario(true));

        $migration = require database_path('migrations/2026_08_28_000800_v1_0_19_break_transfer_loops.php');
        $migration->up();

        $this->assertSame('cancelled', $reverseTransfer->fresh()->status);
        $this->assertSame('in_processing', $asset->fresh()->status);
        $this->assertTrue(
            $asset->fresh()->custody()->where('partner_profile_id', $recovery->id)->whereNull('released_at')->exists()
        );
        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'event_type' => 'TRANSFER_LOOP_BLOCKED',
        ]);
    }

    #[Test]
    public function recovery_cannot_create_a_new_transfer_back_to_repair_after_repair_was_ruled_out(): void
    {
        extract($this->scenario(true));
        $reverseTransfer->update(['status'=>'cancelled','cancelled_at'=>now(),'cancel_reason'=>'test reset']);
        $asset->update(['status'=>'needs_transfer']);

        $this->actingAs($recoveryUser)
            ->get(route('partner.transfers.create', $asset))
            ->assertRedirect(route('partner.assets.show', $asset))
            ->assertSessionHasErrors('flow');
    }
}
