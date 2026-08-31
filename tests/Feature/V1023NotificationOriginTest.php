<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\SirkelNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1023NotificationOriginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function in_app_notification_stores_same_origin_path_instead_of_absolute_host(): void
    {
        $user = $this->user();

        app(NotificationService::class)->send(
            $user,
            'Permintaan baru',
            'Ada permintaan yang perlu ditinjau.',
            'http://localhost:8000/partner/requests/15?source=notification',
            false
        );

        $notification = $user->fresh()->notifications()->firstOrFail();

        $this->assertSame(
            '/partner/requests/15?source=notification',
            $notification->data['url']
        );
    }

    #[Test]
    public function legacy_absolute_notification_is_opened_on_current_origin(): void
    {
        $user = $this->user();
        $user->notify(new SirkelNotification(
            'Notifikasi lama',
            'URL ini dibuat sebelum normalisasi same-origin.',
            'http://localhost:8000/partner/requests/21?from=legacy'
        ));
        $notification = $user->fresh()->notifications()->firstOrFail();

        $this->actingAs($user)
            ->get(route('notifications.read', $notification->id))
            ->assertRedirect('/partner/requests/21?from=legacy');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function faq_heading_reads_like_product_copy_not_an_seo_block(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Hal yang sering ditanyakan.')
            ->assertDontSee('Tentang SIRKEL dan e-waste.');
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Mitra Notifikasi',
            'email' => 'notif-origin@test.local',
            'password' => 'password123',
            'role' => UserRole::PARTNER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
    }
}
