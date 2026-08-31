<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetCustody, DeviceCategory, DeviceGroup, PartnerProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SplitBatchFlowV1016Test extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'received'): array
    {
        $group = DeviceGroup::create(['code'=>'split16','name'=>'Split','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create([
            'device_group_id'=>$group->id,'code'=>'cable-split16','name'=>'Kabel Uji','supports_batch'=>true,
            'special_handling_possible'=>false,'sort_order'=>1,'active'=>true,
        ]);
        $owner = User::create([
            'name'=>'Warga Split','email'=>'split-owner@test.local','password'=>'password123','role'=>UserRole::USER,
            'email_verified_at'=>now(),'profile_completed_at'=>now(),
        ]);
        $partnerUser = User::create([
            'name'=>'Mitra Split','email'=>'split-partner@test.local','password'=>'password123','role'=>UserRole::PARTNER,
            'email_verified_at'=>now(),'profile_completed_at'=>now(),
        ]);
        $profile = PartnerProfile::create([
            'user_id'=>$partnerUser->id,'business_name'=>'Mitra Split','responsible_name'=>'Operator','phone'=>'6281311111111',
            'address'=>'Surabaya','district'=>'Rungkut','village'=>'Kalirungkut','latitude'=>-7.32,'longitude'=>112.76,
            'pickup_radius_km'=>10,'accepting_requests'=>true,'verification_status'=>'approved','admin_status'=>'active',
        ]);
        $asset = Asset::create([
            'passport_code'=>'SRK-B-SPLIT16','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,
            'tracking_type'=>'batch','quantity'=>4,'status'=>$status,'handover_type'=>'free_handover',
            'preliminary_path'=>'PARTS_RECOVERY','verified_weight_kg'=>1.0,'core_locked_at'=>now(),
        ]);
        AssetCustody::create([
            'asset_id'=>$asset->id,'partner_profile_id'=>$profile->id,'received_by_user_id'=>$partnerUser->id,'received_at'=>now(),
        ]);
        return compact('owner','partnerUser','profile','asset');
    }

    #[Test]
    public function active_batch_can_be_split_atomically_into_traceable_children(): void
    {
        extract($this->fixture());

        $this->actingAs($partnerUser)
            ->postJson(route('partner.assets.split',$asset),[
                'parts'=>[
                    ['quantity'=>2,'condition_class'=>'layak','verified_weight_kg'=>0.5],
                    ['quantity'=>2,'condition_class'=>'rusak','verified_weight_kg'=>0.5],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('SPLIT_TO_SUB_BATCHES',$asset->fresh()->final_path);
        $this->assertSame('closed',$asset->fresh()->status);
        $children = Asset::where('parent_asset_id',$asset->id)->get();
        $this->assertCount(2,$children);
        $this->assertSame(4,$children->sum('quantity'));
        $this->assertEqualsWithDelta(1.0,(float)$children->sum('verified_weight_kg'),0.001);
        foreach ($children as $child) {
            $this->assertTrue(AssetCustody::where('asset_id',$child->id)->where('partner_profile_id',$profile->id)->whereNull('released_at')->exists());
        }
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->whereNull('released_at')->exists());
    }

    #[Test]
    public function batch_cannot_be_split_after_a_transfer_decision_has_already_changed_the_flow(): void
    {
        extract($this->fixture('needs_transfer'));

        $this->actingAs($partnerUser)
            ->postJson(route('partner.assets.split',$asset),[
                'parts'=>[
                    ['quantity'=>2,'condition_class'=>'layak','verified_weight_kg'=>0.5],
                    ['quantity'=>2,'condition_class'=>'rusak','verified_weight_kg'=>0.5],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('assets',['parent_asset_id'=>$asset->id]);
        $this->assertNull($asset->fresh()->final_path);
    }
}
