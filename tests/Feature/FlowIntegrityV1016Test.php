<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetCustody, DeviceCategory, DeviceGroup, HandoverRequest, PartnerCapabilityModel, PartnerProfile, PartnerTransfer, User};
use App\Services\RuleEngine;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Notification, Storage};
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlowIntegrityV1016Test extends TestCase
{
    use RefreshDatabase;

    private function base(): array
    {
        Notification::fake();
        $group = DeviceGroup::create(['code'=>'flow16','name'=>'Flow 16','sort_order'=>1,'active'=>true]);
        $category = DeviceCategory::create([
            'device_group_id'=>$group->id,
            'code'=>'flow16-device',
            'name'=>'Perangkat Uji',
            'supports_batch'=>false,
            'special_handling_possible'=>false,
            'sort_order'=>1,
            'active'=>true,
        ]);
        $owner = User::create([
            'name'=>'Warga Flow',
            'email'=>'flow16-owner@test.local',
            'password'=>'password123',
            'role'=>UserRole::USER,
            'email_verified_at'=>now(),
            'profile_completed_at'=>now(),
            'whatsapp'=>'6281216161616',
            'district'=>'Rungkut',
            'village'=>'Kalirungkut',
        ]);
        return compact('group','category','owner');
    }

    private function partner(DeviceCategory $category, string $email, array $caps): array
    {
        $user = User::create([
            'name'=>$email,
            'email'=>$email,
            'password'=>'password123',
            'role'=>UserRole::PARTNER,
            'email_verified_at'=>now(),
            'profile_completed_at'=>now(),
        ]);
        $profile = PartnerProfile::create([
            'user_id'=>$user->id,
            'business_name'=>'Mitra '.$email,
            'responsible_name'=>'Operator',
            'phone'=>'6281316161616',
            'address'=>'Surabaya',
            'district'=>'Rungkut',
            'village'=>'Kalirungkut',
            'latitude'=>-7.32,
            'longitude'=>112.76,
            'pickup_radius_km'=>10,
            'accepting_requests'=>true,
            'verification_status'=>'approved','admin_status'=>'active',
        ]);
        $profile->acceptedCategories()->attach($category->id);
        foreach ($caps as $cap) {
            PartnerCapabilityModel::create(['partner_profile_id'=>$profile->id,'capability'=>$cap,'status'=>'approved']);
        }
        return [$user,$profile];
    }

    private function handledDonationAsset(User $owner, DeviceCategory $category, User $partnerUser, PartnerProfile $profile): Asset
    {
        $asset = Asset::create([
            'passport_code'=>'SRK-I-FLOW16-'.strtoupper(str()->random(4)),
            'owner_user_id'=>$owner->id,
            'device_category_id'=>$category->id,
            'tracking_type'=>'individual',
            'quantity'=>1,
            'status'=>'received',
            'handover_type'=>'donation',
            'preliminary_path'=>'REPAIR_ASSESSMENT',
            'verified_weight_kg'=>1.0,
            'core_locked_at'=>now(),
        ]);
        AssetCustody::create([
            'asset_id'=>$asset->id,
            'partner_profile_id'=>$profile->id,
            'received_by_user_id'=>$partnerUser->id,
            'received_at'=>now(),
        ]);
        return $asset;
    }

    #[Test]
    public function donation_goal_cannot_be_closed_as_repaired_by_repair_only_partner(): void
    {
        extract($this->base());
        [$partnerUser,$partner] = $this->partner($category,'repair16@test.local',['repair']);
        $asset = $this->handledDonationAsset($owner,$category,$partnerUser,$partner);

        $this->actingAs($partnerUser)
            ->postJson(route('partner.assets.assess',$asset),[
                'power_status'=>'partial',
                'damage_level'=>'moderate',
                'repair_feasible'=>'yes',
                'handling_decision'=>'REPAIRED',
                'summary'=>'Perbaikan teknis sudah dilakukan.',
            ])
            ->assertStatus(422);

        $this->assertNull($asset->fresh()->final_path);
        $this->assertDatabaseMissing('asset_assessments',['asset_id'=>$asset->id,'result_path'=>'REPAIRED']);
    }

    #[Test]
    public function donation_after_repair_can_explicitly_continue_to_reuse_donation_partner_only(): void
    {
        extract($this->base());
        [$repairUser,$repair] = $this->partner($category,'repair-transfer@test.local',['repair']);
        [$donationUser,$donation] = $this->partner($category,'donation-target@test.local',['reuse_donation']);
        [$recoveryUser,$recovery] = $this->partner($category,'recovery-wrong@test.local',['recovery']);
        $asset = $this->handledDonationAsset($owner,$category,$repairUser,$repair);

        $this->actingAs($repairUser)
            ->post(route('partner.assets.assess',$asset),[
                'power_status'=>'partial',
                'damage_level'=>'moderate',
                'repair_feasible'=>'yes',
                'handling_decision'=>'TRANSFER_REUSE_DONATION',
                'summary'=>'Perbaikan selesai; barang perlu disalurkan sesuai tujuan donasi warga.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->assertSame('needs_transfer',$asset->fresh()->status);
        $this->assertDatabaseHas('asset_assessments',['asset_id'=>$asset->id,'result_path'=>'TRANSFER_REUSE_DONATION']);

        $this->actingAs($repairUser)
            ->get(route('partner.transfers.create',$asset))
            ->assertOk()
            ->assertSee($donation->business_name)
            ->assertDontSee($recovery->business_name);

        $this->actingAs($repairUser)
            ->postJson(route('partner.transfers.store',$asset),[
                'to_partner_id'=>$recovery->id,
                'note'=>'Target salah layanan.',
            ])
            ->assertStatus(422);

        $this->actingAs($repairUser)
            ->post(route('partner.transfers.store',$asset),[
                'to_partner_id'=>$donation->id,
                'note'=>'Perbaikan selesai. Mohon lanjutkan penyaluran donasi.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->assertSame('transfer_pending',$asset->fresh()->status);
        $transfer = PartnerTransfer::where('asset_id',$asset->id)->where('status','pending')->firstOrFail();

        $this->actingAs($donationUser)
            ->post(route('partner.transfers.receive',$transfer))
            ->assertRedirect(route('partner.assets.show',$asset));

        $this->assertSame('received',$asset->fresh()->status);
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$repair->id)->whereNull('released_at')->exists());
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$donation->id)->whereNull('released_at')->exists());

        // Assessment mitra asal tidak boleh membuat UI mitra tujuan mengira transfer masih diperlukan.
        $this->actingAs($donationUser)
            ->get(route('partner.assets.show',$asset))
            ->assertOk()
            ->assertSee('Catat Pemeriksaan')
            ->assertDontSee('Pilih Mitra Lanjutan');

        $this->actingAs($donationUser)
            ->post(route('partner.assets.assess',$asset),[
                'power_status'=>'normal',
                'damage_level'=>'minor',
                'repair_feasible'=>'unknown',
                'handling_decision'=>'DONATED',
                'summary'=>'Barang sudah disalurkan kepada penerima donasi.',
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $asset->refresh();
        $this->assertNull($asset->final_path);
        $this->assertSame('awaiting_donation_proof',$asset->status);
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$donation->id)->whereNull('released_at')->exists());

        Storage::fake('public');
        $this->actingAs($donationUser)
            ->post(route('partner.assets.donation-proof.store',$asset),[
                'recipient_type'=>'foundation',
                'recipient_name'=>'Yayasan Demo',
                'photo'=>UploadedFile::fake()->image('bukti-donasi.jpg',900,700),
                'latitude'=>-7.32,
                'longitude'=>112.76,
                'location_accuracy_m'=>12,
                'location_label'=>'Rungkut, Surabaya',
                'donated_at'=>now()->subMinute()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('partner.assets.show',$asset));

        $asset->refresh();
        $this->assertSame('DONATED',$asset->final_path);
        $this->assertSame('donated',$asset->status);
        $this->assertDatabaseHas('donation_proofs',[
            'asset_id'=>$asset->id,
            'partner_profile_id'=>$donation->id,
            'recipient_type'=>'foundation',
        ]);
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$donation->id)->whereNull('released_at')->exists());
    }

    #[Test]
    public function target_partner_can_decline_transfer_without_moving_custody(): void
    {
        extract($this->base());
        [$repairUser,$repair] = $this->partner($category,'repair-decline@test.local',['repair']);
        [$donationUser,$donation] = $this->partner($category,'donation-decline@test.local',['reuse_donation']);
        $asset = $this->handledDonationAsset($owner,$category,$repairUser,$repair);

        $this->actingAs($repairUser)->post(route('partner.assets.assess',$asset),[
            'power_status'=>'partial','damage_level'=>'moderate','repair_feasible'=>'yes',
            'handling_decision'=>'TRANSFER_REUSE_DONATION','summary'=>'Perlu penyaluran donasi.',
        ]);
        $this->actingAs($repairUser)->post(route('partner.transfers.store',$asset),[
            'to_partner_id'=>$donation->id,'note'=>'Mohon lanjutkan donasi.',
        ]);
        $transfer = PartnerTransfer::where('asset_id',$asset->id)->where('status','pending')->firstOrFail();

        $this->actingAs($donationUser)
            ->post(route('partner.transfers.decline',$transfer),['reason'=>'Kapasitas penyaluran sedang penuh.'])
            ->assertRedirect(route('partner.dashboard'));

        $this->assertSame('declined',$transfer->fresh()->status);
        $this->assertSame('needs_transfer',$asset->fresh()->status);
        $this->assertTrue(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$repair->id)->whereNull('released_at')->exists());
        $this->assertFalse(AssetCustody::where('asset_id',$asset->id)->where('partner_profile_id',$donation->id)->whereNull('released_at')->exists());
    }

    #[Test]
    public function active_transfer_blocks_conflicting_new_assessment(): void
    {
        extract($this->base());
        [$repairUser,$repair] = $this->partner($category,'repair-block@test.local',['repair']);
        [$donationUser,$donation] = $this->partner($category,'donation-block@test.local',['reuse_donation']);
        $asset = $this->handledDonationAsset($owner,$category,$repairUser,$repair);

        $this->actingAs($repairUser)->post(route('partner.assets.assess',$asset),[
            'power_status'=>'partial','damage_level'=>'moderate','repair_feasible'=>'yes',
            'handling_decision'=>'TRANSFER_REUSE_DONATION','summary'=>'Perlu penyaluran.',
        ]);
        $this->actingAs($repairUser)->post(route('partner.transfers.store',$asset),[
            'to_partner_id'=>$donation->id,'note'=>'Pengalihan aktif.',
        ]);

        $this->actingAs($repairUser)
            ->postJson(route('partner.assets.assess',$asset),[
                'power_status'=>'normal','damage_level'=>'minor','repair_feasible'=>'yes',
                'handling_decision'=>'TRANSFER_RECOVERY','summary'=>'Mencoba mengubah saat transfer aktif.',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function non_sale_handover_cannot_create_value_offer(): void
    {
        extract($this->base());
        [$partnerUser,$partner] = $this->partner($category,'donation-offer@test.local',['reuse_donation']);
        $asset = Asset::create([
            'passport_code'=>'SRK-I-OFFER16','owner_user_id'=>$owner->id,'device_category_id'=>$category->id,
            'tracking_type'=>'individual','quantity'=>1,'status'=>'partner_accepted','handover_type'=>'donation','preliminary_path'=>'DONATION',
        ]);
        $handover = HandoverRequest::create([
            'asset_id'=>$asset->id,'user_id'=>$owner->id,'partner_profile_id'=>$partner->id,'method'=>'dropoff','status'=>'accepted',
        ]);

        $this->actingAs($partnerUser)
            ->postJson(route('partner.requests.offer',$handover),['amount'=>10000,'valid_hours'=>6])
            ->assertStatus(422);
    }

    #[Test]
    public function admin_rules_preserve_donation_priority_for_usable_item(): void
    {
        $this->seed(MasterDataSeeder::class);
        $result = app(RuleEngine::class)->evaluate([
            'power_status'=>'normal',
            'damage_level'=>'none',
            'technician_result'=>'not_checked',
            'user_intent'=>'donate',
        ]);

        $this->assertSame('DONATION',$result['path']);
    }
}
