<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetAssessment, AssetCustody, DeviceCategory, DeviceGroup, PartnerCapabilityModel, PartnerProfile, PartnerTransfer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerTransferInboxV1017Test extends TestCase
{
    use RefreshDatabase;

    private function makePartner(DeviceCategory $category, string $email, string $name, string $capability): array
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
            'phone' => '6281317171717',
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
            'capability' => $capability,
            'status' => 'approved',
        ]);

        return [$user, $profile];
    }

    private function scenario(): array
    {
        $group = DeviceGroup::create(['code'=>'flow17','name'=>'Elektronik Kecil','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create([
            'device_group_id'=>$group->id,
            'code'=>'radio-flow17',
            'name'=>'Radio',
            'sort_order'=>1,
            'active'=>true,
        ]);
        $owner = User::create([
            'name'=>'Warga',
            'email'=>'owner17@test.local',
            'password'=>'password123',
            'role'=>UserRole::USER,
            'email_verified_at'=>now(),
            'profile_completed_at'=>now(),
        ]);
        [$repairUser, $repair] = $this->makePartner($category, 'repair17@test.local', 'Sirkular Service Gunung Anyar', 'repair');
        [$recoveryUser, $recovery] = $this->makePartner($category, 'recovery17@test.local', 'Mitra Recovery Surabaya', 'recovery');

        $asset = Asset::create([
            'passport_code'=>'SRK-I-FLOW17',
            'owner_user_id'=>$owner->id,
            'device_category_id'=>$category->id,
            'custom_item_name'=>'Radio',
            'tracking_type'=>'individual',
            'quantity'=>1,
            'status'=>'transfer_pending',
            'handover_type'=>'free_handover',
            'preliminary_path'=>'REPAIR_ASSESSMENT',
            'verified_weight_kg'=>1.35,
            'core_locked_at'=>now(),
        ]);
        AssetCustody::create([
            'asset_id'=>$asset->id,
            'partner_profile_id'=>$repair->id,
            'received_by_user_id'=>$repairUser->id,
            'received_at'=>now(),
        ]);
        AssetAssessment::create([
            'asset_id'=>$asset->id,
            'assessment_type'=>'partner',
            'assessor_user_id'=>$repairUser->id,
            'answers_json'=>['power_status'=>'off','damage_level'=>'severe','repair_feasible'=>'no'],
            'result_path'=>'TRANSFER_RECOVERY',
            'summary'=>'Tidak layak diperbaiki dan perlu pemulihan material.',
            'verified_weight_kg'=>1.35,
            'verified_at'=>now(),
        ]);
        $transfer = PartnerTransfer::create([
            'asset_id'=>$asset->id,
            'from_partner_id'=>$repair->id,
            'to_partner_id'=>$recovery->id,
            'requested_by_user_id'=>$repairUser->id,
            'required_capability'=>'recovery',
            'status'=>'pending',
            'note'=>'Mohon lanjutkan pemulihan material.',
            'requested_at'=>now(),
        ]);

        return compact('asset','repairUser','repair','recoveryUser','recovery','transfer');
    }

    #[Test]
    public function incoming_partner_transfer_is_visible_in_target_partner_inbox(): void
    {
        extract($this->scenario());

        $this->actingAs($recoveryUser)
            ->get(route('partner.requests.index'))
            ->assertOk()
            ->assertSee('Pengalihan dari Mitra Lain')
            ->assertSee('Radio')
            ->assertSee('Sirkular Service Gunung Anyar')
            ->assertSee('Pemulihan Material')
            ->assertSee('Tinjau Pengalihan');
    }

    #[Test]
    public function target_partner_can_review_transfer_before_confirming_physical_receipt(): void
    {
        extract($this->scenario());

        $this->actingAs($recoveryUser)
            ->get(route('partner.transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Konfirmasi setelah barang tiba.')
            ->assertSee('Tidak layak diperbaiki dan perlu pemulihan material.')
            ->assertSee('Konfirmasi Barang Diterima');

        $this->actingAs($repairUser)
            ->get(route('partner.transfers.show', $transfer))
            ->assertForbidden();
    }

    #[Test]
    public function receiving_transfer_moves_asset_to_target_handling_workspace(): void
    {
        extract($this->scenario());

        $this->actingAs($recoveryUser)
            ->post(route('partner.transfers.receive', $transfer))
            ->assertRedirect(route('partner.assets.show', $asset));

        $this->assertSame('received', $asset->fresh()->status);
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$repair->id)->whereNull('released_at')->exists());
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$recovery->id)->whereNull('released_at')->exists());

        $this->actingAs($recoveryUser)
            ->get(route('partner.assets.index'))
            ->assertOk()
            ->assertSee('Radio');
    }
}
