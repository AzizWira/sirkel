<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{PartnerCapabilityModel, PartnerProfile, Question, QuestionnaireTemplate, User};
use App\Notifications\SirkelNotification;
use App\Services\RuleEngine;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1020ExperienceRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_questionnaire_has_safe_handover_and_contextual_help_for_every_question(): void
    {
        $this->seed(MasterDataSeeder::class);

        $generic = QuestionnaireTemplate::query()->where('code', 'generic-small-electronics')->firstOrFail();
        $intent = $generic->questions()->with('options')->where('code', 'user_intent')->firstOrFail();

        $this->assertSame('Apakah barang/perangkat ini masih berfungsi?', $generic->questions()->where('code', 'power_status')->value('text'));
        $this->assertTrue($intent->options->contains(fn ($option) => $option->value === 'safe_handover'));
        $this->assertSame(
            'Saya ingin menyerahkan/membuangnya dengan aman',
            $intent->options->firstWhere('value', 'safe_handover')->label
        );

        $batteryIntent = QuestionnaireTemplate::query()
            ->where('code', 'battery-safety')
            ->firstOrFail()
            ->questions()
            ->with('options')
            ->where('code', 'user_intent')
            ->firstOrFail();
        $this->assertTrue($batteryIntent->options->contains(fn ($option) => $option->value === 'safe_handover'));

        Question::query()->each(function (Question $question): void {
            $this->assertNotSame('', trim((string) $question->help_text), "Help text kosong untuk {$question->code}");
        });
    }

    #[Test]
    public function safe_handover_remains_an_intent_and_does_not_force_material_recovery(): void
    {
        $this->seed(MasterDataSeeder::class);
        $engine = app(RuleEngine::class);

        $this->assertSame('REUSE', $engine->evaluate([
            'power_status' => 'normal',
            'damage_level' => 'none',
            'technician_result' => 'not_checked',
            'user_intent' => 'safe_handover',
        ])['path']);

        $this->assertSame('REPAIR_ASSESSMENT', $engine->evaluate([
            'power_status' => 'partial',
            'damage_level' => 'minor',
            'technician_result' => 'not_checked',
            'user_intent' => 'safe_handover',
        ])['path']);

        $this->assertSame('PARTS_RECOVERY', $engine->evaluate([
            'power_status' => 'off',
            'damage_level' => 'severe',
            'technician_result' => 'not_repairable',
            'user_intent' => 'safe_handover',
        ])['path']);

        $this->assertSame('SPECIAL_HANDLING', $engine->evaluate([
            'power_status' => 'off',
            'battery_swollen' => 'yes',
            'user_intent' => 'safe_handover',
        ])['path']);

        $this->assertSame('SPECIAL_HANDLING', $engine->evaluate([
            'power_status' => 'off',
            'battery_leaking' => 'yes',
            'user_intent' => 'safe_handover',
        ])['path']);
    }

    #[Test]
    public function approved_partner_is_managed_instead_of_being_approved_or_rejected_again(): void
    {
        [$admin, $partner] = $this->approvedPartnerFixture();

        $response = $this->actingAs($admin)->get(route('admin.partners.show', $partner));

        $response->assertOk()
            ->assertSee('Pengelolaan Mitra')
            ->assertSee('Simpan Perubahan')
            ->assertSee('Nonaktifkan Mitra')
            ->assertDontSee('Setujui Mitra')
            ->assertDontSee('Tolak Pengajuan');

        $this->actingAs($admin)->post(route('admin.partners.status', $partner), [
            'admin_status' => 'inactive',
        ])->assertSessionHas('success');

        $partner->refresh();
        $this->assertSame('approved', $partner->verification_status);
        $this->assertSame('inactive', $partner->admin_status);
        $this->assertFalse($partner->accepting_requests);

        $response = $this->actingAs($admin)->get(route('admin.partners.show', $partner));
        $response->assertSee('Aktifkan Kembali')->assertDontSee('Setujui Mitra');
    }

    #[Test]
    public function notifications_show_distinct_read_states_and_read_all_action(): void
    {
        $user = User::create([
            'name' => 'Warga Notif',
            'email' => 'notif-v1020@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $user->notify(new SirkelNotification('Belum dibaca', 'Pesan baru', route('notifications.index')));
        $user->notify(new SirkelNotification('Sudah dibaca', 'Pesan lama', route('notifications.index')));
        $user->notifications()->get()->first(fn ($notification) => ($notification->data['title'] ?? null) === 'Sudah dibaca')?->markAsRead();

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk()
            ->assertSee('Baca semua')
            ->assertSee('Belum dibaca')
            ->assertSee('Sudah dibaca')
            ->assertSee('notification-item is-unread', false)
            ->assertSee('notification-item is-read', false);

        $this->actingAs($user)->post(route('notifications.read-all'))->assertSessionHas('success', 'Semua notifikasi sudah dibaca.');
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function indonesian_validation_language_never_exposes_uploaded_translation_key(): void
    {
        $messages = require lang_path('id/validation.php');

        $this->assertArrayHasKey('uploaded', $messages);
        $this->assertStringNotContainsString('validation.', $messages['uploaded']);
        $this->assertSame('kategori barang yang diterima', $messages['attributes']['category_ids']);
        $this->assertSame('foto KTP penanggung jawab', $messages['attributes']['ktp']);
    }

    #[Test]
    public function brand_assets_for_icon_light_and_dark_wordmarks_exist(): void
    {
        foreach ([
            public_path('brand/sirkel-icon.png'),
            public_path('brand/sirkel-favicon.png'),
            public_path('brand/sirkel-wordmark-light.png'),
            public_path('brand/sirkel-wordmark-dark.png'),
        ] as $file) {
            $this->assertFileExists($file);
            $this->assertGreaterThan(1000, filesize($file));
        }
    }

    private function approvedPartnerFixture(): array
    {
        $admin = User::create([
            'name' => 'Admin SIRKEL',
            'email' => 'admin-v1020@test.local',
            'password' => 'password123',
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $partnerUser = User::create([
            'name' => 'Mitra SIRKEL',
            'email' => 'partner-v1020@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $partner = PartnerProfile::create([
            'user_id' => $partnerUser->id,
            'business_name' => 'Mitra Uji V1.0.20',
            'responsible_name' => 'Penanggung Jawab',
            'phone' => '6281200000000',
            'address' => 'Surabaya',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar',
            'latitude' => -7.3340000,
            'longitude' => 112.7860000,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        PartnerCapabilityModel::create([
            'partner_profile_id' => $partner->id,
            'capability' => 'repair',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return [$admin, $partner];
    }
}
