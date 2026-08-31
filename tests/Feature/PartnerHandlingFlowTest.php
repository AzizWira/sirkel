<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetCustody, DeviceCategory, DeviceGroup, HandoverRequest, PartnerCapabilityModel, PartnerProfile, PartnerTransfer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerHandlingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupFlow(): array
    {
        $group = DeviceGroup::create(['code'=>'small-household','name'=>'Elektronik Rumah Tangga Kecil','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create(['device_group_id'=>$group->id,'code'=>'toy-car','name'=>'Mainan Mobil Elektronik','sort_order'=>1,'active'=>true]);
        $owner = User::create(['name'=>'Warga','email'=>'flow-owner@test.local','password'=>'password123','role'=>UserRole::USER,'email_verified_at'=>now(),'profile_completed_at'=>now(),'whatsapp'=>'628121111111','district'=>'Rungkut','village'=>'Kali Rungkut']);
        $partnerUser = User::create(['name'=>'Operator Mitra','email'=>'flow-partner@test.local','password'=>'password123','role'=>UserRole::PARTNER,'email_verified_at'=>now(),'profile_completed_at'=>now()]);
        $partner = PartnerProfile::create(['user_id'=>$partnerUser->id,'business_name'=>'Mitra Uji','responsible_name'=>'Operator','phone'=>'628131111111','address'=>'Surabaya','district'=>'Rungkut','village'=>'Kali Rungkut','latitude'=>-7.32,'longitude'=>112.76,'pickup_radius_km'=>10,'accepting_requests'=>true,'verification_status'=>'approved','admin_status'=>'active']);
        PartnerCapabilityModel::create(['partner_profile_id'=>$partner->id,'capability'=>'repair','status'=>'approved']);
        $asset = Asset::create(['passport_code'=>'SRK-I-FLOW14','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'status'=>'partner_accepted','handover_type'=>'free_handover','preliminary_path'=>'REPAIR_ASSESSMENT']);
        $handover = HandoverRequest::create(['asset_id'=>$asset->id,'user_id'=>$owner->id,'partner_profile_id'=>$partner->id,'method'=>'dropoff','status'=>'accepted']);
        return compact('owner','partnerUser','partner','asset','handover');
    }

    #[Test]
    public function receiving_item_finishes_handover_but_moves_item_to_handling_workspace(): void
    {
        Notification::fake();
        extract($this->setupFlow());

        $this->actingAs($partnerUser)
            ->post(route('partner.requests.receive', $handover), ['verified_weight_kg'=>'1.000'])
            ->assertRedirect(route('partner.assets.show', $asset));

        $this->assertSame('completed', $handover->fresh()->status);
        $this->assertSame('received', $asset->fresh()->status);
        $this->assertNotNull($asset->fresh()->core_locked_at);
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$partner->id)->whereNull('released_at')->exists());

        $this->actingAs($partnerUser)->get(route('partner.assets.show',$asset))
            ->assertOk()
            ->assertSee('Penanganan Barang')
            ->assertSee('Periksa kondisi fisik dan catat apa yang benar-benar terjadi');
    }

    #[Test]
    public function partner_can_mark_item_for_transfer_without_faking_a_final_outcome(): void
    {
        Notification::fake();
        extract($this->setupFlow());
        $asset->update(['core_locked_at'=>now(),'verified_weight_kg'=>1,'status'=>'received']);
        AssetCustody::create(['asset_id'=>$asset->id,'partner_profile_id'=>$partner->id,'received_by_user_id'=>$partnerUser->id,'received_at'=>now()]);

        $this->actingAs($partnerUser)
            ->post(route('partner.assets.assess',$asset), [
                'power_status'=>'off',
                'damage_level'=>'severe',
                'repair_feasible'=>'no',
                'handling_decision'=>'TRANSFER_RECOVERY',
                'summary'=>'Tidak layak diperbaiki oleh layanan kami dan perlu diteruskan.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $fresh = $asset->fresh();
        $this->assertNull($fresh->final_path);
        $this->assertSame('needs_transfer',$fresh->status);
        $this->assertTrue($fresh->custody()->where('partner_profile_id',$partner->id)->whereNull('released_at')->exists());
        $this->assertDatabaseHas('asset_assessments',['asset_id'=>$asset->id,'result_path'=>'TRANSFER_RECOVERY']);
    }

    #[Test]
    public function transferred_item_becomes_actionable_in_target_partner_handling_workspace(): void
    {
        Notification::fake();
        extract($this->setupFlow());
        $asset->update(['core_locked_at'=>now(),'verified_weight_kg'=>1,'status'=>'received']);
        AssetCustody::create(['asset_id'=>$asset->id,'partner_profile_id'=>$partner->id,'received_by_user_id'=>$partnerUser->id,'received_at'=>now()]);
        $partner->acceptedCategories()->attach($asset->device_category_id);

        $targetUser = User::create([
            'name'=>'Operator Recovery',
            'email'=>'flow-recovery@test.local',
            'password'=>'password123',
            'role'=>UserRole::PARTNER,
            'email_verified_at'=>now(),
            'profile_completed_at'=>now(),
        ]);
        $target = PartnerProfile::create([
            'user_id'=>$targetUser->id,
            'business_name'=>'Mitra Pemulihan Uji',
            'responsible_name'=>'Operator Recovery',
            'phone'=>'628139999999',
            'address'=>'Surabaya',
            'district'=>'Rungkut',
            'village'=>'Kali Rungkut',
            'latitude'=>-7.33,
            'longitude'=>112.77,
            'pickup_radius_km'=>10,
            'accepting_requests'=>true,
            'verification_status'=>'approved','admin_status'=>'active',
        ]);
        $target->acceptedCategories()->attach($asset->device_category_id);
        PartnerCapabilityModel::create(['partner_profile_id'=>$target->id,'capability'=>'recovery','status'=>'approved']);

        $this->actingAs($partnerUser)
            ->post(route('partner.assets.assess',$asset), [
                'power_status'=>'off',
                'damage_level'=>'severe',
                'repair_feasible'=>'no',
                'handling_decision'=>'TRANSFER_RECOVERY',
                'summary'=>'Tidak layak diperbaiki dan perlu diteruskan ke pemulihan material.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->actingAs($partnerUser)
            ->post(route('partner.transfers.store',$asset), [
                'to_partner_id'=>$target->id,
                'note'=>'Tidak dapat diselesaikan oleh layanan perbaikan; lanjutkan pemulihan.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $transfer = PartnerTransfer::where('asset_id',$asset->id)->firstOrFail();

        $this->actingAs($targetUser)
            ->post(route('partner.transfers.receive',$transfer))
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->assertSame('received',$asset->fresh()->status);
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$partner->id)->whereNull('released_at')->exists());
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$target->id)->whereNull('released_at')->exists());

        $this->actingAs($targetUser)->get(route('partner.assets.show',$asset))
            ->assertOk()
            ->assertSee('Penanganan Barang');
    }

    #[Test]
    public function verified_final_outcome_releases_partner_custody(): void
    {
        Notification::fake();
        extract($this->setupFlow());
        $asset->update(['core_locked_at'=>now(),'verified_weight_kg'=>1,'status'=>'received']);
        AssetCustody::create(['asset_id'=>$asset->id,'partner_profile_id'=>$partner->id,'received_by_user_id'=>$partnerUser->id,'received_at'=>now()]);

        $this->actingAs($partnerUser)
            ->post(route('partner.assets.assess',$asset), [
                'power_status'=>'normal',
                'damage_level'=>'minor',
                'repair_feasible'=>'yes',
                'handling_decision'=>'REPAIRED',
                'summary'=>'Perbaikan selesai dan perangkat kembali berfungsi.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->assertSame('REPAIRED',$asset->fresh()->final_path);
        $this->assertFalse($asset->fresh()->custody()->where('partner_profile_id',$partner->id)->whereNull('released_at')->exists());
    }
}
