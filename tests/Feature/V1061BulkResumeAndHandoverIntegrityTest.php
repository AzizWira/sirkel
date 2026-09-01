<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{
    Asset,
    DeviceCategory,
    DeviceGroup,
    HandoverRequest,
    IntakeSession,
    IntakeSessionItem,
    IssueReport,
    PartnerCapabilityModel,
    PartnerProfile,
    User
};
use App\Services\{AiQuotaService, RegionService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1061BulkResumeAndHandoverIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function citizen(string $email = 'citizen-v1061@example.test'): User
    {
        return User::create([
            'name' => 'Warga V1061',
            'email' => $email,
            'password' => 'password123',
            'role' => UserRole::USER,
            'whatsapp' => '6281212345678',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
        ]);
    }

    private function category(string $code, string $groupCode): DeviceCategory
    {
        $group = DeviceGroup::create([
            'code' => $groupCode,
            'name' => strtoupper($groupCode),
            'sort_order' => 1,
            'active' => true,
        ]);

        return DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => $code,
            'name' => strtoupper($code),
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    private function reviewAsset(User $user, DeviceCategory $category, string $code): Asset
    {
        return Asset::create([
            'passport_code' => $code,
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'description' => 'Barang untuk pengujian alur v1.0.61.',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'status' => 'matching',
        ]);
    }

    private function reviewSession(User $user, array $assets): IntakeSession
    {
        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_REVIEW,
            'quota_consumed_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ($assets as $index => $asset) {
            IntakeSessionItem::create([
                'intake_session_id' => $session->id,
                'asset_id' => $asset->id,
                'source' => 'bulk_ai',
                'sort_order' => $index + 1,
                'assessment_completed_at' => now(),
            ]);
        }
        return $session;
    }

    #[Test]
    public function bulk_home_exposes_unfinished_session_without_consuming_new_quota(): void
    {
        $user = $this->citizen();
        $category = $this->category('resume-device', 'resume-group');
        $asset = $this->reviewAsset($user, $category, 'SRK-B-RESUME61');
        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_DRAFT,
            'quota_consumed_at' => now(),
        ]);
        IntakeSessionItem::create([
            'intake_session_id' => $session->id,
            'asset_id' => $asset->id,
            'source' => 'bulk_ai',
            'sort_order' => 1,
        ]);

        $usedBefore = app(AiQuotaService::class)->status($user, AiQuotaService::BULK_AI)['used'];
        $response = $this->actingAs($user)->get(route('user.bulk.create'));

        $response->assertOk()
            ->assertSee('Lanjutkan Bulk AI yang belum selesai')
            ->assertSee('Lanjutkan Sesi')
            ->assertSee(route('user.bulk.edit', $session), false);
        $this->assertSame($usedBefore, app(AiQuotaService::class)->status($user, AiQuotaService::BULK_AI)['used']);
    }

    #[Test]
    public function pausing_bulk_questionnaire_returns_to_bulk_resume_home(): void
    {
        $user = $this->citizen('pause-v1061@example.test');
        $category = $this->category('pause-device', 'pause-group');
        $asset = $this->reviewAsset($user, $category, 'SRK-B-PAUSE61');
        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_QUESTIONNAIRE,
            'quota_consumed_at' => now(),
            'adaptive_questions_json' => [[
                'id' => 'condition',
                'text' => 'Bagaimana kondisi barang?',
                'type' => 'single',
                'targets' => [$asset->public_id],
                'required' => true,
                'options' => [['value' => 'ok', 'label' => 'Baik']],
            ]],
        ]);
        IntakeSessionItem::create([
            'intake_session_id' => $session->id,
            'asset_id' => $asset->id,
            'source' => 'bulk_ai',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('user.bulk.answers.pause', $session), [
            'answers' => ['condition' => 'ok'],
        ]);

        $response->assertRedirect(route('user.bulk.create'));
        $this->assertSame('ok', $session->fresh()->adaptive_answers_json['condition']);
    }

    #[Test]
    public function multi_handover_rejects_dates_after_current_year_and_non_half_hour_times(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $user = $this->citizen('schedule-v1061@example.test');
        $category = $this->category('schedule-device', 'schedule-group');
        $asset = $this->reviewAsset($user, $category, 'SRK-B-SCHEDULE61');
        $session = $this->reviewSession($user, [$asset]);

        $response = $this->actingAs($user)->from(route('user.intake.handover.form', $session))->post(
            route('user.intake.handover.match', $session),
            [
                'method' => 'pickup',
                'latitude' => -7.3340,
                'longitude' => 112.7860,
                'address' => 'Gunung Anyar, Surabaya',
                'district' => 'Gunung Anyar',
                'village' => 'Gunung Anyar Tambak',
                'requested_date' => '2027-01-01',
                'time_start' => '09:15',
                'time_end' => '10:00',
                'handover_types' => [$asset->public_id => 'free_handover'],
            ]
        );

        $response->assertRedirect(route('user.intake.handover.form', $session))
            ->assertSessionHasErrors(['requested_date', 'time_start']);
        $this->assertNull($session->fresh()->handover_context_json);
    }

    #[Test]
    public function mixed_multi_handover_is_atomic_and_routes_unmatched_item_to_admin_assistance(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $user = $this->citizen('mixed-v1061@example.test');
        $matchedCategory = $this->category('matched-device', 'matched-group');
        $unmatchedCategory = $this->category('unmatched-device', 'unmatched-group');
        $matchedAsset = $this->reviewAsset($user, $matchedCategory, 'SRK-B-MATCHED61');
        $unmatchedAsset = $this->reviewAsset($user, $unmatchedCategory, 'SRK-B-UNMATCHED61');
        $session = $this->reviewSession($user, [$matchedAsset, $unmatchedAsset]);
        $session->update(['handover_context_json' => [
            'method' => 'pickup',
            'latitude' => -7.3340,
            'longitude' => 112.7860,
            'address' => 'Gunung Anyar, Surabaya',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
            'requested_date' => '2026-09-05',
            'time_start' => '09:00',
            'time_end' => '10:00',
            'handover_types' => [
                $matchedAsset->public_id => 'free_handover',
                $unmatchedAsset->public_id => 'free_handover',
            ],
        ]]);

        $partnerUser = User::create([
            'name' => 'Mitra V1061',
            'email' => 'partner-v1061@example.test',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Cocok V1061',
            'responsible_name' => 'Penanggung Jawab',
            'phone' => '628111111111',
            'address' => 'Gunung Anyar, Surabaya',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
            'latitude' => -7.3342,
            'longitude' => 112.7862,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        $partner->acceptedCategories()->attach($matchedCategory->id);
        foreach (['pickup', 'repair'] as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $partner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }

        $response = $this->actingAs($user)->post(route('user.intake.handover.create', $session), [
            'partners' => [$matchedAsset->public_id => $partner->public_id],
            'assist' => [$unmatchedAsset->public_id => '1'],
            'ownership_acknowledgement' => '1',
        ]);

        $response->assertRedirect(route('user.activity'));
        $this->assertDatabaseCount('handover_requests', 1);
        $this->assertDatabaseHas('handover_requests', [
            'asset_id' => $matchedAsset->id,
            'partner_profile_id' => $partner->id,
        ]);
        $this->assertDatabaseHas('issue_reports', [
            'asset_id' => $unmatchedAsset->id,
            'category' => 'matching_help',
            'status' => 'open',
            'handover_request_id' => null,
        ]);
        $issue = IssueReport::where('asset_id', $unmatchedAsset->id)->firstOrFail();
        $this->assertSame('needs_sirkel_assistance', $issue->context_json['outcome']);
        $this->assertSame('matching_assistance', $unmatchedAsset->fresh()->status);
        $this->assertSame(IntakeSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertSame(1, HandoverRequest::where('asset_id', $matchedAsset->id)->count());
    }

    #[Test]
    public function reverse_geocoding_normalizes_to_surabaya_master_and_keeps_manual_fallback(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'display_name' => 'Gunung Anyar Tambak, Kecamatan Gunung Anyar, Surabaya, Jawa Timur',
                'address' => [
                    'village' => 'Kelurahan Gunung Anyar Tambak',
                    'city_district' => 'Kecamatan Gunung Anyar',
                    'city' => 'Surabaya',
                ],
            ], 200),
        ]);

        $location = app(RegionService::class)->reverseGeocode(-7.3340, 112.7860);

        $this->assertSame('Gunung Anyar', $location['district']);
        $this->assertSame('Gunung Anyar Tambak', $location['village']);
        $this->assertNull(app(RegionService::class)->reverseGeocode(-6.2, 106.8));
    }

    #[Test]
    public function v1062_ui_contract_keeps_bulk_flow_out_of_standard_cart_and_uses_unified_photo_picker(): void
    {
        $bulkCreate = file_get_contents(resource_path('views/user/bulk/create.blade.php'));
        $bulkEdit = file_get_contents(resource_path('views/user/bulk/edit.blade.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $multiPartners = file_get_contents(resource_path('views/user/handovers/multi-partners.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-bulk-photo-gallery', $bulkCreate);
        $this->assertStringContainsString('data-bulk-photo-camera', $bulkCreate);
        $this->assertStringContainsString('data-bulk-photo-preview', $bulkCreate);
        $this->assertStringNotContainsString('bulk-photo-slots', $bulkCreate);
        $this->assertStringContainsString('function bindBulkPhotoPicker', $js);
        $this->assertStringContainsString("form?.addEventListener('formdata'", $js);
        $this->assertStringNotContainsString('Simpan Semua ke Keranjang', $bulkEdit);
        $this->assertStringNotContainsString("name('bulk.cart')", $routes);
        $this->assertStringContainsString('Lanjutkan Bulk Sekarang', $bulkEdit);
        $this->assertStringContainsString('Butuh Bantuan SIRKEL', $multiPartners);
        $this->assertStringContainsString('Kirim Semua Permintaan', $multiPartners);
    }
}
