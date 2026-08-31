<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset,DeviceCategory,DeviceGroup,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PassportPrivacyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_passport_does_not_render_owner_pii(): void
    {
        $group=DeviceGroup::create(['code'=>'mobile','name'=>'Mobile','sort_order'=>1,'active'=>true]);
        $category=DeviceCategory::create(['device_group_id'=>$group->id,'code'=>'smartphone','name'=>'Smartphone','sort_order'=>1,'active'=>true]);
        $owner=User::create(['name'=>'Nama Sangat Rahasia','email'=>'rahasia@example.test','password'=>'password123','role'=>UserRole::USER,'whatsapp'=>'6281299999999','district'=>'Gunung Anyar','village'=>'Gunung Anyar Tambak']);
        $asset=Asset::create(['passport_code'=>'SRK-I-PRIVACY','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'origin_district'=>'Gunung Anyar']);

        $response=$this->get(route('passport.show',$asset->passport_code));
        $response->assertOk()->assertSee('Gunung Anyar')->assertDontSee('Nama Sangat Rahasia')->assertDontSee('6281299999999')->assertDontSee('rahasia@example.test');
    }
}
