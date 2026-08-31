<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, AssetAssessment, DeviceCategory, DeviceGroup, HandoverRequest, PartnerProfile, Question, QuestionnaireTemplate, QuestionOption, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetDetailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_sees_complete_asset_data_assessment_and_latest_completed_handover(): void
    {
        $group = DeviceGroup::create([
            'code' => 'small-household',
            'name' => 'Elektronik Rumah Tangga Kecil',
            'sort_order' => 1,
            'active' => true,
        ]);

        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'blender',
            'name' => 'Blender',
            'sort_order' => 1,
            'active' => true,
        ]);

        $template = QuestionnaireTemplate::create([
            'device_category_id' => $category->id,
            'code' => 'blender-check',
            'name' => 'Cek Kondisi Blender',
            'active' => true,
        ]);
        $question = Question::create([
            'questionnaire_template_id' => $template->id,
            'code' => 'power_status',
            'text' => 'Apakah perangkat masih dapat menyala?',
            'type' => 'single',
            'required' => true,
            'sort_order' => 1,
        ]);
        QuestionOption::create([
            'question_id' => $question->id,
            'value' => 'off',
            'label' => 'Tidak menyala',
            'sort_order' => 1,
        ]);

        $owner = User::create([
            'name' => 'Warga',
            'email' => 'asset-detail-owner@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '628121111111',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar',
        ]);

        $partnerUser = User::create([
            'name' => 'Mitra',
            'email' => 'asset-detail-partner@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Repair Gunung Anyar',
            'responsible_name' => 'Operator',
            'phone' => '628131111111',
            'address' => 'Surabaya',
            'district' => 'Gunung Anyar',
            'latitude' => -7.334,
            'longitude' => 112.786,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);

        $asset = Asset::create([
            'passport_code' => 'SRK-I-DETAIL',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'brand' => 'Philips',
            'model_name' => 'HR-TEST',
            'description' => 'Tidak menyala ketika tombol ditekan.',
            'quantity' => 1,
            'estimated_weight_kg' => 1.250,
            'dormant_since' => '2025-08-01',
            'origin_district' => 'Gunung Anyar',
            'origin_village' => 'Rungkut Menanggal',
            'preliminary_path' => 'TECHNICAL_ASSESSMENT',
            'status' => 'received',
            'handover_type' => 'sale',
            'core_locked_at' => now(),
            'verified_weight_kg' => 1.100,
        ]);

        AssetAssessment::create([
            'asset_id' => $asset->id,
            'assessment_type' => 'user',
            'assessor_user_id' => $owner->id,
            'answers_json' => ['power_status' => 'off'],
            'result_path' => 'TECHNICAL_ASSESSMENT',
            'summary' => 'Perlu pemeriksaan teknis oleh mitra.',
        ]);

        HandoverRequest::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'status' => 'completed',
            'pickup_address' => 'Jl. Contoh No. 1',
            'pickup_district' => 'Gunung Anyar',
            'pickup_village' => 'Rungkut Menanggal',
            'distance_km' => 2.4,
            'within_radius' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('user.assets.show', $asset));

        $response->assertOk()
            ->assertSee('Informasi Barang')
            ->assertSee('Philips')
            ->assertSee('HR-TEST')
            ->assertSee('1,250 kg')
            ->assertSee('Rungkut Menanggal')
            ->assertSee('Hasil Cek Kondisi Warga')
            ->assertSee('Tidak menyala')
            ->assertSee('Perlu pemeriksaan teknis oleh mitra.')
            ->assertSee('Penyerahan Terakhir')
            ->assertSee('Mitra Repair Gunung Anyar')
            ->assertSee('Jl. Contoh No. 1');
    }
}
