<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{PartnerProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1034CitizenPartnerMultiAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, UserRole $role = UserRole::USER): User
    {
        return User::create([
            'name' => 'Akun Uji',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '6281210340000',
            'district' => 'Rungkut',
            'village' => 'Kali Rungkut',
        ]);
    }

    private function approvedPartner(User $user, bool $acknowledged = false): PartnerProfile
    {
        return PartnerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Mitra Multi Akses',
            'responsible_name' => $user->name,
            'phone' => '6281210340000',
            'address' => 'Rungkut, Surabaya',
            'district' => 'Rungkut',
            'village' => 'Kali Rungkut',
            'latitude' => -7.318,
            'longitude' => 112.775,
            'pickup_radius_km' => 10,
            'accepting_requests' => true,
            'verification_status' => 'approved',
            'admin_status' => 'active',
            'verified_at' => now(),
            'partner_access_granted_at' => now(),
            'approval_acknowledged_at' => $acknowledged ? now() : null,
        ]);
    }

    #[Test]
    public function citizen_only_login_goes_directly_to_citizen_dashboard(): void
    {
        $user = $this->user('citizen-only-v1034@test.local');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'))
          ->assertSessionHas('active_role', 'user');
    }

    #[Test]
    public function approved_partner_keeps_citizen_access_and_chooses_access_after_login(): void
    {
        $user = $this->user('multi-v1034@test.local');
        $this->approvedPartner($user, true);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('access.choose'));

        $this->get(route('access.choose'))
            ->assertOk()
            ->assertSee('Masuk sebagai Warga')
            ->assertSee('Masuk sebagai Mitra');

        $this->post(route('access.choose.store'), ['access' => 'partner'])
            ->assertRedirect(route('partner.dashboard'))
            ->assertSessionHas('active_role', 'partner');

        $this->assertSame(UserRole::USER, $user->fresh()->role);
    }

    #[Test]
    public function approval_acknowledgement_hides_onboarding_without_removing_citizen_role(): void
    {
        $user = $this->user('approved-v1034@test.local');
        $profile = $this->approvedPartner($user, false);

        $this->actingAs($user)
            ->withSession(['active_role' => 'user'])
            ->get(route('user.become-partner.create'))
            ->assertOk()
            ->assertSee('Pengajuan Anda diterima')
            ->assertSee('Paham');

        $this->actingAs($user)
            ->withSession(['active_role' => 'user'])
            ->post(route('user.become-partner.acknowledge'))
            ->assertRedirect(route('user.dashboard'));

        $this->assertNotNull($profile->fresh()->approval_acknowledged_at);
        $this->assertSame(UserRole::USER, $user->fresh()->role);
    }

    #[Test]
    public function pending_partner_application_does_not_create_partner_login_choice(): void
    {
        $user = $this->user('pending-v1034@test.local');
        PartnerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Mitra Pending',
            'responsible_name' => $user->name,
            'phone' => '6281210340000',
            'address' => 'Rungkut, Surabaya',
            'district' => 'Rungkut',
            'village' => 'Kali Rungkut',
            'latitude' => -7.318,
            'longitude' => 112.775,
            'pickup_radius_km' => 10,
            'accepting_requests' => false,
            'verification_status' => 'pending',
            'admin_status' => 'inactive',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'))
          ->assertSessionHas('active_role', 'user');

        $this->get(route('user.become-partner.create'))
            ->assertOk()
            ->assertSee('Sedang menunggu verifikasi');
    }

    #[Test]
    public function acknowledging_approval_removes_jadi_mitra_menu_in_citizen_mode(): void
    {
        $user = $this->user('menu-v1034@test.local');
        $this->approvedPartner($user, true);

        $this->actingAs($user)
            ->withSession(['active_role' => 'user'])
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('Jadi Mitra');
    }

    #[Test]
    public function active_access_role_separates_citizen_and_partner_routes(): void
    {
        $user = $this->user('route-mode-v1034@test.local');
        $this->approvedPartner($user, true);

        $this->actingAs($user)
            ->withSession(['active_role' => 'user'])
            ->get(route('partner.dashboard'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_role' => 'partner'])
            ->get(route('user.dashboard'))
            ->assertForbidden();
    }
}
