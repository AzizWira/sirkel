<?php

namespace Tests\Feature;

use App\Models\{DeviceCategory, DeviceGroup};
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1054StandardPhotoScopeAndElectronicToyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function uncertain_toy_vehicle_can_be_offered_as_electronic_toy_with_user_confirmation(): void
    {
        config()->set('sirkel.ai.api_key', 'test-key');

        $group = DeviceGroup::create([
            'code' => 'gaming-entertainment',
            'name' => 'Gaming & Hiburan',
            'sort_order' => 1,
            'active' => true,
        ]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'other-gaming-electronics',
            'name' => 'Perangkat Gaming Lainnya',
            'supports_batch' => false,
            'special_handling_possible' => false,
            'active' => true,
            'sort_order' => 1,
        ])->load('group');

        Http::fake([
            '*' => Http::response([
                'output_text' => json_encode([
                    'eligibility_status' => 'uncertain',
                    'eligibility_reason' => 'Objek tampak seperti mobil mainan, tetapi baterai atau motor tidak terlihat dari luar.',
                    'scope_status' => 'single_type',
                    'scope_reason' => '',
                    'detected_types' => ['Mobil mainan'],
                    'detected_name' => 'Mobil mainan',
                    'category_code' => null,
                    'custom_item_name' => null,
                    'visible_quantity' => 1,
                    'same_item_group' => true,
                    'description' => null,
                    'confidence' => 0.82,
                ], JSON_UNESCAPED_UNICODE),
                'usage' => [],
            ], 200),
        ]);

        $photo = UploadedFile::fake()->createWithContent('mobil-mainan.jpg', 'fake-image-content');
        $suggestion = app(AiService::class)->draftIntake([$photo], collect([$category]));

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion['eligible']);
        $this->assertSame($category->id, $suggestion['category_id']);
        $this->assertSame('other-gaming-electronics', $suggestion['category_code']);
        $this->assertTrue($suggestion['needs_electronic_confirmation']);
        $this->assertStringContainsString('baterai/listrik/remote', $suggestion['eligibility_reason']);
    }

    #[Test]
    public function standard_photo_ai_rejects_multiple_distinct_device_types_and_sends_them_to_bulk(): void
    {
        config()->set('sirkel.ai.api_key', 'test-key');

        $group = DeviceGroup::create([
            'code' => 'other-electronics',
            'name' => 'Elektronik Lainnya',
            'sort_order' => 1,
            'active' => true,
        ]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'uncategorized-electronics',
            'name' => 'Elektronik Lainnya / Belum Tahu Kategorinya',
            'supports_batch' => true,
            'special_handling_possible' => false,
            'active' => true,
            'sort_order' => 1,
        ])->load('group');

        Http::fake([
            '*' => Http::response([
                'output_text' => json_encode([
                    'eligibility_status' => 'supported',
                    'eligibility_reason' => 'Semua objek merupakan elektronik fisik.',
                    'scope_status' => 'multiple_types',
                    'scope_reason' => 'Terlihat kulkas, mesin cuci, dan microwave. Pendaftaran biasa hanya untuk satu jenis barang.',
                    'detected_types' => ['Kulkas', 'Mesin Cuci', 'Microwave'],
                    'detected_name' => 'Beberapa elektronik rumah tangga',
                    'category_code' => null,
                    'custom_item_name' => null,
                    'visible_quantity' => 3,
                    'same_item_group' => false,
                    'description' => null,
                    'confidence' => 0.96,
                ], JSON_UNESCAPED_UNICODE),
                'usage' => [],
            ], 200),
        ]);

        $photo = UploadedFile::fake()->createWithContent('campuran.jpg', 'fake-image-content');
        $suggestion = app(AiService::class)->draftIntake([$photo], collect([$category]));

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion['eligible']);
        $this->assertTrue($suggestion['requires_bulk']);
        $this->assertSame('multiple_types', $suggestion['scope_status']);
        $this->assertSame(['Kulkas', 'Mesin Cuci', 'Microwave'], $suggestion['detected_types']);
        $this->assertArrayNotHasKey('category_id', $suggestion);
        $this->assertStringContainsString('satu jenis barang', $suggestion['eligibility_reason']);
    }

    #[Test]
    public function standard_intake_ui_explains_single_type_scope_and_offers_bulk_when_needed(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $service = file_get_contents(app_path('Services/AiService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AssetController.php'));

        $this->assertStringContainsString('Foto satu jenis barang * — 1–3 foto', $view);
        $this->assertStringContainsString('data-asset-photo-scope-modal', $view);
        $this->assertStringContainsString('Satu jenis barang saja', $view);
        $this->assertStringContainsString('data-asset-ai-bulk', $view);
        $this->assertStringContainsString('data-asset-photo-scope-status', $view);
        $this->assertStringContainsString('Beberapa jenis barang terdeteksi', $javascript);
        $this->assertStringContainsString('suggestion.requires_bulk', $javascript);
        $this->assertStringContainsString('scopeNoticeShown', $javascript);
        $this->assertStringContainsString("state.scopeInput?.value === 'multiple_types'", $javascript);

        $this->assertStringContainsString('scope_status wajib salah satu: single_type, multiple_types, uncertain', $service);
        $this->assertStringContainsString('mobil RC/remote', $service);
        $this->assertStringContainsString('looksLikeElectronicToyCandidate', $service);
        $this->assertStringContainsString("photo_scope_status') === 'multiple_types'", $controller);
    }
}
