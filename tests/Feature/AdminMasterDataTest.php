<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{DeviceCategory, DeviceGroup, QuestionnaireTemplate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_open_reference_data_with_questionnaire_category_relation(): void
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

        QuestionnaireTemplate::create([
            'device_category_id' => $category->id,
            'code' => 'blender-basic-check',
            'name' => 'Pemeriksaan Awal Blender',
            'active' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin SIRKEL',
            'email' => 'admin-master-data@test.local',
            'password' => 'password123',
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'whatsapp' => '628121111111',
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master.index'));

        $response->assertOk();
        $response->assertSee('Pemeriksaan Awal Blender');
        $response->assertSee('Blender');
    }

    #[Test]
    public function questionnaire_template_belongs_to_its_device_category(): void
    {
        $group = DeviceGroup::create([
            'code' => 'computing',
            'name' => 'Komputasi',
            'sort_order' => 1,
            'active' => true,
        ]);

        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'laptop',
            'name' => 'Laptop',
            'sort_order' => 1,
            'active' => true,
        ]);

        $template = QuestionnaireTemplate::create([
            'device_category_id' => $category->id,
            'code' => 'laptop-basic-check',
            'name' => 'Pemeriksaan Awal Laptop',
            'active' => true,
        ]);

        $this->assertSame($category->id, $template->deviceCategory()->firstOrFail()->id);
        $this->assertSame('Laptop', $template->deviceCategory->name);
    }
}
