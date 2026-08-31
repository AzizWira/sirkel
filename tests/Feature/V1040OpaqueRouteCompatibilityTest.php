<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, DeviceGroup, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1040OpaqueRouteCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(): array
    {
        $owner = User::create([
            'name' => 'Warga',
            'email' => 'v1040-owner@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $group = DeviceGroup::create(['code' => 'v1040', 'name' => 'V1040', 'sort_order' => 1, 'active' => true]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'v1040-device',
            'name' => 'Perangkat V1040',
            'supports_batch' => false,
            'special_handling_possible' => false,
            'sort_order' => 1,
            'active' => true,
        ]);
        $asset = Asset::create([
            'passport_code' => 'SRK-I-V1040',
            'owner_user_id' => $owner->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'description' => 'Perangkat uji kompatibilitas route.',
            'status' => 'registered',
            'origin_district' => 'Rungkut',
            'origin_village' => 'Kalirungkut',
        ]);

        return [$owner, $asset];
    }

    #[Test]
    public function generated_url_is_opaque_but_numeric_legacy_binding_still_resolves(): void
    {
        [$owner, $asset] = $this->makeAsset();

        $this->assertStringContainsString($asset->public_id, route('user.assets.show', $asset));
        $assetModel = new Asset;
        $this->assertSame($asset->id, $assetModel->resolveRouteBindingQuery($assetModel->newQuery(), (string) $asset->id)->firstOrFail()->id);
        $this->assertSame($asset->id, $assetModel->resolveRouteBindingQuery($assetModel->newQuery(), $asset->public_id)->firstOrFail()->id);
    }

    #[Test]
    public function ordinary_test_requests_keep_real_forbidden_status_while_browser_navigation_redirects(): void
    {
        [$owner, $asset] = $this->makeAsset();
        $other = User::create([
            'name' => 'Warga Lain',
            'email' => 'v1040-other@test.local',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($other)
            ->withSession(['active_role' => 'user'])
            ->get(route('user.assets.show', $asset))
            ->assertForbidden();

        $this->actingAs($other)
            ->withSession(['active_role' => 'user'])
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->get(route('user.assets.show', $asset))
            ->assertRedirect(route('user.assets.index'));
    }
}
