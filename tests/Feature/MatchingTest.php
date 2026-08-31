<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset,DeviceCategory,DeviceGroup,PartnerCapabilityModel,PartnerProfile,User};
use App\Services\PartnerMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    private function category(): DeviceCategory
    {
        $group = DeviceGroup::create(['code'=>'test','name'=>'Test','sort_order'=>1,'active'=>true]);
        return DeviceCategory::create(['device_group_id'=>$group->id,'code'=>'smartphone','name'=>'Smartphone','sort_order'=>1,'active'=>true]);
    }

    private function partner(string $email, DeviceCategory $category, float $lat, float $lng, float $radius, array $caps): PartnerProfile
    {
        $user = User::create(['name'=>$email,'email'=>$email,'password'=>'password123','role'=>UserRole::PARTNER,'email_verified_at'=>now(),'profile_completed_at'=>now()]);
        $partner = PartnerProfile::create(['user_id'=>$user->id,'business_name'=>$email,'responsible_name'=>'Demo','phone'=>'628111111111','address'=>'Surabaya','district'=>'Gunung Anyar','latitude'=>$lat,'longitude'=>$lng,'pickup_radius_km'=>$radius,'accepting_requests'=>true,'verification_status'=>'approved','admin_status'=>'active']);
        $partner->acceptedCategories()->attach($category->id);
        foreach ($caps as $cap) PartnerCapabilityModel::create(['partner_profile_id'=>$partner->id,'capability'=>$cap,'status'=>'approved']);
        return $partner;
    }

    #[Test]
    public function pickup_keeps_outside_radius_partner_but_prioritizes_inside_radius(): void
    {
        $category = $this->category();
        $owner = User::create(['name'=>'Warga','email'=>'warga@test.local','password'=>'password123','role'=>UserRole::USER]);
        $asset = Asset::create(['passport_code'=>'SRK-I-TEST01','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'preliminary_path'=>'REPAIR_ASSESSMENT']);
        $inside = $this->partner('inside@test.local',$category,-7.3375,112.7850,10,['pickup','repair']);
        $outside = $this->partner('outside@test.local',$category,-7.20,112.65,2,['pickup','repair']);

        $results = app(PartnerMatchingService::class)->match($asset,'pickup',-7.3340,112.7860,'sale');
        $this->assertCount(2,$results);
        $this->assertSame($inside->id,$results->first()->id);
        $this->assertTrue((bool)$results->first()->within_radius);
        $this->assertFalse((bool)$results->last()->within_radius);
        $this->assertSame($outside->id,$results->last()->id);
    }

    #[Test]
    public function donation_handover_never_overrides_special_handling_path(): void
    {
        $category = $this->category();
        $owner = User::create(['name'=>'Warga','email'=>'warga2@test.local','password'=>'password123','role'=>UserRole::USER]);
        $asset = Asset::create(['passport_code'=>'SRK-I-TEST02','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'preliminary_path'=>'SPECIAL_HANDLING']);
        $reuse = $this->partner('reuse@test.local',$category,-7.3340,112.7860,10,['pickup','reuse_donation']);
        $special = $this->partner('special@test.local',$category,-7.3350,112.7860,10,['pickup','special_handling']);

        $results = app(PartnerMatchingService::class)->match($asset,'pickup',-7.3340,112.7860,'donation');
        $this->assertCount(1,$results);
        $this->assertSame($special->id,$results->first()->id);
        $this->assertNotSame($reuse->id,$results->first()->id);
    }

    #[Test]
    public function admin_inactive_partner_is_excluded_from_new_matching(): void
    {
        $category = $this->category();
        $owner = User::create(['name'=>'Warga','email'=>'warga-inactive@test.local','password'=>'password123','role'=>UserRole::USER]);
        $asset = Asset::create(['passport_code'=>'SRK-I-INACTIVE','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'preliminary_path'=>'REPAIR_ASSESSMENT']);
        $active = $this->partner('active@test.local',$category,-7.3340,112.7860,10,['pickup','repair']);
        $inactive = $this->partner('inactive@test.local',$category,-7.3350,112.7860,10,['pickup','repair']);
        $inactive->update(['admin_status'=>'inactive']);

        $results = app(PartnerMatchingService::class)->match($asset,'pickup',-7.3340,112.7860,'sale');

        $this->assertCount(1,$results);
        $this->assertSame($active->id,$results->first()->id);
        $this->assertFalse($results->contains('id',$inactive->id));
    }

}
