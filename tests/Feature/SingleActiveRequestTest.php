<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset,DeviceCategory,DeviceGroup,HandoverRequest,PartnerCapabilityModel,PartnerProfile,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SingleActiveRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function asset_cannot_create_a_second_active_handover_request(): void
    {
        $group = DeviceGroup::create(['code'=>'mobile','name'=>'Mobile','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create(['device_group_id'=>$group->id,'code'=>'smartphone','name'=>'Smartphone','sort_order'=>1,'active'=>true]);
        $owner = User::create(['name'=>'Warga','email'=>'owner@test.local','password'=>'password123','role'=>UserRole::USER,'email_verified_at'=>now(),'profile_completed_at'=>now(),'whatsapp'=>'628121111111','district'=>'Gunung Anyar','village'=>'Gunung Anyar']);
        $asset = Asset::create(['passport_code'=>'SRK-I-ACTIVE','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'preliminary_path'=>'REPAIR_ASSESSMENT','status'=>'matching']);

        $makePartner = function (string $email, float $lng) use ($category) {
            $user = User::create(['name'=>$email,'email'=>$email,'password'=>'password123','role'=>UserRole::PARTNER,'email_verified_at'=>now(),'profile_completed_at'=>now()]);
            $partner = PartnerProfile::create(['user_id'=>$user->id,'business_name'=>$email,'responsible_name'=>'Operator','phone'=>'628131111111','address'=>'Surabaya','district'=>'Gunung Anyar','latitude'=>-7.334,'longitude'=>$lng,'pickup_radius_km'=>10,'accepting_requests'=>true,'verification_status'=>'approved','admin_status'=>'active']);
            $partner->acceptedCategories()->attach($category->id);
            foreach (['pickup','repair'] as $cap) PartnerCapabilityModel::create(['partner_profile_id'=>$partner->id,'capability'=>$cap,'status'=>'approved']);
            return $partner;
        };
        $p1=$makePartner('p1@test.local',112.786);
        $p2=$makePartner('p2@test.local',112.787);

        $this->actingAs($owner)->postJson(route('user.handovers.create',$asset),[
            'partner_profile_id'=>$p1->id,'method'=>'pickup','handover_type'=>'sale','latitude'=>-7.334,'longitude'=>112.786,
            'address'=>'Alamat A','district'=>'Gunung Anyar','village'=>'Gunung Anyar',
            'requested_date'=>now()->addDay()->toDateString(),'time_start'=>'09:00','time_end'=>'11:00','ownership_acknowledgement'=>'1',
        ])->assertRedirect(route('user.assets.show',$asset));

        $this->assertSame(1,HandoverRequest::where('asset_id',$asset->id)->count());
        $this->actingAs($owner)->postJson(route('user.handovers.create',$asset),[
            'partner_profile_id'=>$p2->id,'method'=>'pickup','handover_type'=>'sale','latitude'=>-7.334,'longitude'=>112.786,
            'address'=>'Alamat A','district'=>'Gunung Anyar','village'=>'Gunung Anyar',
            'requested_date'=>now()->addDay()->toDateString(),'time_start'=>'09:00','time_end'=>'11:00','ownership_acknowledgement'=>'1',
        ])->assertStatus(422);
        $this->assertSame(1,HandoverRequest::where('asset_id',$asset->id)->count());
    }
}
