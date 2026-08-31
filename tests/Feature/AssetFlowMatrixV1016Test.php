<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\AssetFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetFlowMatrixV1016Test extends TestCase
{
    use RefreshDatabase;

    private function setupAsset(string $handoverType = 'free_handover', string $preliminaryPath = 'REPAIR_ASSESSMENT'): array
    {
        $suffix = strtolower(str()->random(6));
        $group = DeviceGroup::create(['code'=>'matrix16-'.$suffix,'name'=>'Matrix','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create([
            'device_group_id'=>$group->id,'code'=>'matrix-device-'.$suffix,'name'=>'Perangkat Matrix','supports_batch'=>false,
            'special_handling_possible'=>true,'sort_order'=>1,'active'=>true,
        ]);
        $owner = User::create([
            'name'=>'Warga Matrix','email'=>'matrix-owner-'.str()->random(5).'@test.local','password'=>'password123',
            'role'=>UserRole::USER,'email_verified_at'=>now(),'profile_completed_at'=>now(),
        ]);
        $asset = Asset::create([
            'passport_code'=>'SRK-I-MATRIX-'.strtoupper(str()->random(5)),'owner_user_id'=>$owner->id,
            'device_category_id'=>$category->id,'tracking_type'=>'individual','quantity'=>1,'status'=>'received',
            'handover_type'=>$handoverType,'preliminary_path'=>$preliminaryPath,'core_locked_at'=>now(),
        ]);
        return compact('asset','category');
    }

    private function profile(DeviceCategory $category, array $capabilities): PartnerProfile
    {
        $user = User::create([
            'name'=>'Operator Matrix','email'=>'matrix-partner-'.str()->random(5).'@test.local','password'=>'password123',
            'role'=>UserRole::PARTNER,'email_verified_at'=>now(),'profile_completed_at'=>now(),
        ]);
        $profile = PartnerProfile::create([
            'user_id'=>$user->id,'business_name'=>'Mitra Matrix '.str()->random(3),'responsible_name'=>'Operator',
            'phone'=>'6281312345678','address'=>'Surabaya','district'=>'Rungkut','village'=>'Kalirungkut',
            'latitude'=>-7.32,'longitude'=>112.76,'pickup_radius_km'=>10,'accepting_requests'=>true,
            'verification_status'=>'approved','admin_status'=>'active',
        ]);
        $profile->acceptedCategories()->attach($category->id);
        foreach ($capabilities as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id'=>$profile->id,'capability'=>$capability,'status'=>'approved',
            ]);
        }
        return $profile->load('capabilities');
    }

    private function codes(array $options): array
    {
        return array_values(array_column($options, 'code'));
    }

    #[Test]
    public function donation_repair_is_intermediate_and_must_continue_to_donation_or_other_valid_path(): void
    {
        extract($this->setupAsset('donation','REPAIR_ASSESSMENT'));
        $repair = $this->profile($category,['repair']);
        $flow = app(AssetFlowService::class);

        $completion = $this->codes($flow->completionOptions($asset,$repair));
        $transfer = $this->codes($flow->transferOptions($asset,$repair));

        $this->assertNotContains('REPAIRED',$completion);
        $this->assertContains('TRANSFER_REUSE_DONATION',$transfer);
        $this->assertContains('TRANSFER_RECOVERY',$transfer);
    }

    #[Test]
    public function donation_partner_can_only_finish_donation_when_item_is_recorded_as_usable(): void
    {
        extract($this->setupAsset('donation','DONATION'));
        $donation = $this->profile($category,['reuse_donation']);
        $flow = app(AssetFlowService::class);

        $this->assertContains('DONATED',$this->codes($flow->completionOptions($asset,$donation)));
        $this->assertNotContains('REUSED',$this->codes($flow->completionOptions($asset,$donation)));
        $this->assertNotNull($flow->assessmentConflictMessage($asset,$donation,'DONATED',[
            'power_status'=>'off','damage_level'=>'severe','repair_feasible'=>'no',
        ]));
        $this->assertNull($flow->assessmentConflictMessage($asset,$donation,'DONATED',[
            'power_status'=>'normal','damage_level'=>'minor','repair_feasible'=>'unknown',
        ]));
    }

    #[Test]
    public function normal_sale_or_free_handover_can_finish_as_repaired_after_real_repair(): void
    {
        foreach (['sale','free_handover'] as $type) {
            extract($this->setupAsset($type,'REPAIR_ASSESSMENT'));
            $repair = $this->profile($category,['repair']);
            $flow = app(AssetFlowService::class);
            $this->assertContains('REPAIRED',$this->codes($flow->completionOptions($asset,$repair)));
            $this->assertNull($flow->assessmentConflictMessage($asset,$repair,'REPAIRED',[
                'power_status'=>'normal','damage_level'=>'minor','repair_feasible'=>'yes',
            ]));
        }
    }

    #[Test]
    public function special_handling_partner_cannot_claim_recovery_without_recovery_capability(): void
    {
        extract($this->setupAsset('free_handover','SPECIAL_HANDLING'));
        $special = $this->profile($category,['special_handling']);
        $flow = app(AssetFlowService::class);

        $completion = $this->codes($flow->completionOptions($asset,$special));
        $transfer = $this->codes($flow->transferOptions($asset,$special));

        $this->assertNotContains('RECOVERY_CONFIRMED',$completion);
        $this->assertNotContains('PARTS_RECOVERED',$completion);
        $this->assertContains('TRANSFER_RECOVERY',$transfer);
    }

    #[Test]
    public function recovery_receipt_is_not_a_verified_final_outcome(): void
    {
        extract($this->setupAsset('free_handover','RECOVERY'));
        $recovery = $this->profile($category,['recovery']);
        $flow = app(AssetFlowService::class);

        $completion = $this->codes($flow->completionOptions($asset,$recovery));
        $this->assertContains('PARTS_RECOVERED',$completion);
        $this->assertContains('RECOVERY_CONFIRMED',$completion);
        $this->assertNotContains('RECEIVED_BY_RECOVERY_PARTNER',$completion);
        $this->assertFalse(\App\Support\SirkelUi::isVerifiedOutcome('RECEIVED_BY_RECOVERY_PARTNER'));
    }
}
