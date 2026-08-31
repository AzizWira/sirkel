<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1039OpaqueRoutesAndGracefulFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Warga Test',
            'email' => $email,
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'district' => 'Rungkut',
            'village' => 'Kalirungkut',
        ]);
    }

    private function assetFor(User $owner): Asset
    {
        $group = DeviceGroup::firstOrCreate(
            ['code' => 'opaque-test'],
            ['name' => 'Opaque Test', 'sort_order' => 1, 'active' => true]
        );
        $category = DeviceCategory::firstOrCreate(
            ['code' => 'opaque-device'],
            [
                'device_group_id' => $group->id,
                'name' => 'Perangkat Opaque',
                'supports_batch' => false,
                'special_handling_possible' => false,
                'sort_order' => 1,
                'active' => true,
            ]
        );

        return Asset::create([
            'passport_code' => 'SRK-I-OPAQUE-'.strtoupper(str()->random(5)),
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'description' => 'Perangkat untuk pengujian public id.',
            'status' => 'registered',
            'origin_district' => 'Rungkut',
            'origin_village' => 'Kalirungkut',
        ]);
    }

    #[Test]
    public function asset_urls_use_opaque_public_ids_instead_of_database_ids(): void
    {
        $owner = $this->user('opaque-owner@test.local');
        $asset = $this->assetFor($owner);

        $this->assertNotNull($asset->public_id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $asset->public_id);
        $this->assertStringContainsString('/app/barang/'.$asset->public_id, route('user.assets.show', $asset));
        $this->assertStringNotContainsString('/app/barang/'.$asset->id, route('user.assets.show', $asset));
    }

    #[Test]
    public function random_or_other_users_asset_redirects_to_barang_list_instead_of_error_page(): void
    {
        $owner = $this->user('opaque-real-owner@test.local');
        $other = $this->user('opaque-other@test.local');
        $asset = $this->assetFor($owner);

        $this->actingAs($other)
            ->withSession(['active_role' => 'user'])
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->get('/app/barang/01JZZZZZZZZZZZZZZZZZZZZZZZ')
            ->assertRedirect(route('user.assets.index'));

        $this->actingAs($other)
            ->withSession(['active_role' => 'user'])
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->get(route('user.assets.show', $asset))
            ->assertRedirect(route('user.assets.index'));
    }

    #[Test]
    public function protected_get_from_a_different_active_access_returns_to_that_access_home(): void
    {
        $user = $this->user('opaque-admin@test.local');
        $user->update(['role' => UserRole::ADMIN]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'admin'])
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->get('/app/barang/01JZZZZZZZZZZZZZZZZZZZZZZZ')
            ->assertRedirect(route('admin.dashboard'));
    }
}
