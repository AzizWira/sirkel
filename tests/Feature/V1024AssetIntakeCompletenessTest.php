<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeviceCategory;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1024AssetIntakeCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(): User
    {
        return User::create([
            'name' => 'Warga Demo',
            'email' => 'warga-v1024@example.test',
            'password' => 'password123',
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '6281212345678',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
        ]);
    }

    #[Test]
    public function selecting_only_a_device_category_cannot_register_an_asset(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->citizen();
        $category = DeviceCategory::where('code', 'toaster')->firstOrFail();

        $this->actingAs($user)
            ->from(route('user.assets.create'))
            ->post(route('user.assets.store'), [
                'device_category_id' => $category->id,
                'tracking_type' => 'individual',
                'quantity' => 1,
                'origin_district' => 'Gunung Anyar',
                'origin_village' => 'Gunung Anyar Tambak',
            ])
            ->assertRedirect(route('user.assets.create'))
            ->assertSessionHasErrors(['description', 'photos']);
    }

    #[Test]
    public function asset_can_continue_to_condition_check_after_minimum_intake_is_complete(): void
    {
        Storage::fake('public');
        $this->seed(MasterDataSeeder::class);
        $user = $this->citizen();
        $category = DeviceCategory::where('code', 'toaster')->firstOrFail();

        $response = $this->actingAs($user)->post(route('user.assets.store'), [
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'quantity' => 1,
            'origin_district' => 'Gunung Anyar',
            'origin_village' => 'Gunung Anyar Tambak',
            'description' => 'Pemanggang menyala tetapi elemen pemanas tidak lagi panas.',
            'photos' => [UploadedFile::fake()->image('toaster.jpg', 900, 700)],
        ]);

        $asset = $user->assets()->latest('id')->firstOrFail();
        $response->assertRedirect(route('user.assets.assessment', $asset));
        $this->assertSame('registered', $asset->status);
        $this->assertCount(1, $asset->photos()->get());
    }
}
