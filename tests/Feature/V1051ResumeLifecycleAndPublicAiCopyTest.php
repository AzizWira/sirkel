<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, HandoverRequest, IntakeSession, IntakeSessionItem, PartnerCapabilityModel, PartnerProfile, User};
use App\Services\IntakeSessionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1051ResumeLifecycleAndPublicAiCopyTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(string $email = 'warga-v1051@example.test'): User
    {
        return User::create([
            'name' => 'Warga v1.0.51',
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

    private function category(): DeviceCategory
    {
        $group = DeviceGroup::create([
            'code' => 'v1051-mobile',
            'name' => 'Perangkat Uji v1.0.51',
            'sort_order' => 1,
            'active' => true,
        ]);

        return DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'v1051-phone',
            'name' => 'Ponsel Uji v1.0.51',
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    private function reviewSession(User $user, array $assets): IntakeSession
    {
        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_STANDARD,
            'status' => IntakeSession::STATUS_REVIEW,
            'current_position' => 1,
            'completed_at' => now(),
        ]);

        foreach ($assets as $index => $asset) {
            IntakeSessionItem::create([
                'intake_session_id' => $session->id,
                'asset_id' => $asset->id,
                'source' => 'cart',
                'sort_order' => $index + 1,
                'assessment_completed_at' => now(),
            ]);
        }

        return $session;
    }

    private function partner(DeviceCategory $category): PartnerProfile
    {
        $user = User::create([
            'name' => 'Mitra v1.0.51',
            'email' => 'mitra-v1051@example.test',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $partner = PartnerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Mitra v1.0.51',
            'responsible_name' => 'Operator',
            'phone' => '6281311111111',
            'address' => 'Gunung Anyar, Surabaya',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
            'latitude' => -7.334,
            'longitude' => 112.786,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
        ]);
        $partner->acceptedCategories()->attach($category->id);

        foreach (['pickup', 'repair'] as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $partner->id,
                'capability' => $capability,
                'status' => 'approved',
            ]);
        }

        return $partner;
    }

    #[Test]
    public function finalized_review_session_is_reconciled_and_disappears_from_resumable_processes(): void
    {
        $user = $this->citizen();
        $category = $this->category();
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1051-DONE',
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'final_path' => 'REPAIRED',
            'status' => 'repaired',
        ]);
        $session = $this->reviewSession($user, [$asset]);

        $this->actingAs($user)
            ->get(route('user.cart.index'))
            ->assertOk()
            ->assertDontSee('Proses yang dapat dilanjutkan');

        $session->refresh();
        $this->assertSame(IntakeSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);

        $this->actingAs($user)
            ->get(route('user.intake.handover.form', $session))
            ->assertRedirect(route('user.intake.review', $session));

        $this->actingAs($user)
            ->get(route('user.intake.review', $session))
            ->assertOk()
            ->assertSee('Proses ini sudah dilanjutkan')
            ->assertDontSee('Atur Penyerahan Semua');
    }

    #[Test]
    public function review_session_keeps_only_items_that_have_never_entered_a_handover_lifecycle(): void
    {
        $user = $this->citizen();
        $category = $this->category();
        $partner = $this->partner($category);

        $alreadyRequested = Asset::create([
            'passport_code' => 'SRK-I-V1051-OLD',
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'status' => 'matching',
        ]);
        $stillActionable = Asset::create([
            'passport_code' => 'SRK-I-V1051-NEXT',
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'status' => 'matching',
        ]);
        $session = $this->reviewSession($user, [$alreadyRequested, $stillActionable]);

        HandoverRequest::create([
            'asset_id' => $alreadyRequested->id,
            'user_id' => $user->id,
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'status' => 'cancelled_by_user',
            'pickup_latitude' => -7.334,
            'pickup_longitude' => 112.786,
            'pickup_district' => 'Gunung Anyar',
            'pickup_village' => 'Gunung Anyar Tambak',
            'schedule_status' => 'requested',
        ]);

        $state = app(IntakeSessionStateService::class);
        $state->reconcile($session);
        $actionable = $state->actionableItems($session->fresh());

        $this->assertSame(IntakeSession::STATUS_REVIEW, $session->fresh()->status);
        $this->assertCount(1, $actionable);
        $this->assertSame($stillActionable->id, $actionable->first()->asset_id);
    }

    #[Test]
    public function single_item_handover_closes_the_originating_review_session(): void
    {
        $user = $this->citizen();
        $category = $this->category();
        $partner = $this->partner($category);
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1051-SINGLE',
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'preliminary_path' => 'REPAIR_ASSESSMENT',
            'status' => 'matching',
        ]);
        $session = $this->reviewSession($user, [$asset]);

        $this->actingAs($user)->post(route('user.handovers.create', $asset), [
            'partner_profile_id' => $partner->id,
            'method' => 'pickup',
            'handover_type' => 'sale',
            'latitude' => -7.334,
            'longitude' => 112.786,
            'address' => 'Alamat pickup uji',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
            'requested_date' => now()->addDay()->toDateString(),
            'time_start' => '09:00',
            'time_end' => '11:00',
            'ownership_acknowledgement' => '1',
        ])->assertRedirect(route('user.assets.show', $asset));

        $this->assertSame(1, HandoverRequest::where('asset_id', $asset->id)->count());
        $this->assertSame(IntakeSession::STATUS_COMPLETED, $session->fresh()->status);
    }

    #[Test]
    public function public_ai_copy_is_optional_human_readable_and_does_not_expose_internal_decision_terms(): void
    {
        $home = file_get_contents(resource_path('views/public/home.blade.php'));
        $education = file_get_contents(resource_path('views/public/education.blade.php'));
        $citizenViews = implode("\n", array_map(
            fn (string $relative) => file_get_contents(resource_path('views/'.$relative)),
            [
                'user/assets/create.blade.php',
                'user/intake/standard.blade.php',
                'user/intake/review.blade.php',
                'user/bulk/create.blade.php',
                'user/bulk/edit.blade.php',
                'user/bulk/questionnaire.blade.php',
                'user/handovers/multi-form.blade.php',
            ]
        ));

        $this->assertStringContainsString('Bantuan AI', $home);
        $this->assertStringContainsString('Tetap bisa tanpa AI.', $home);
        $this->assertStringContainsString('Apakah AI menentukan hasil akhir barang?', $home);
        $this->assertStringContainsString('bantuan AI dapat dipakai secara opsional', $education);

        foreach (['Rule Engine', 'Adaptive Bulk Questionnaire', 'Pemeriksaan Standard', 'progres disimpan sebagai draft', 'hard maximum', 'signal_key', 'output_text', 'Responses API'] as $technicalTerm) {
            $this->assertStringNotContainsString($technicalTerm, $home."\n".$education."\n".$citizenViews);
        }

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('AI sedang memeriksa foto yang Anda pilih', $javascript);
        $this->assertStringNotContainsString('Mengirim foto ke AI karena Anda memilih', $javascript);
        $this->assertStringNotContainsString('Tidak ada field yang berubah', $javascript);
        $this->assertStringNotContainsString('AI belum dijalankan untuk foto yang dipilih', $javascript);
    }
}
