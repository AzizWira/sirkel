<?php

namespace Tests\Feature;

use App\Models\{DeviceCategory, DeviceGroup};
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1033AiPhotoGuardAndSelectiveApplyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_electronic_or_screenshot_photo_is_not_mapped_to_other_electronics(): void
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
                    'eligibility_status' => 'unsupported',
                    'eligibility_reason' => 'Gambar berupa tangkapan layar aplikasi Zoom, bukan foto barang elektronik fisik.',
                    'detected_name' => 'Tampilan layar Zoom',
                    'category_code' => null,
                    'custom_item_name' => null,
                    'visible_quantity' => null,
                    'same_item_group' => false,
                    'description' => null,
                    'confidence' => 0.99,
                ], JSON_UNESCAPED_UNICODE),
                'usage' => [],
            ], 200),
        ]);

        $photo = UploadedFile::fake()->createWithContent('zoom.png', 'fake-image-content');
        $suggestion = app(AiService::class)->draftIntake([$photo], collect([$category]));

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion['eligible']);
        $this->assertSame('unsupported', $suggestion['eligibility_status']);
        $this->assertStringContainsString('Zoom', $suggestion['eligibility_reason']);
        $this->assertArrayNotHasKey('category_id', $suggestion);
        $this->assertArrayNotHasKey('description', $suggestion);
    }

    #[Test]
    public function ai_modal_supports_selective_field_apply_and_explicit_rejection_state(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $service = file_get_contents(app_path('Services/AiService.php'));

        $this->assertStringContainsString('data-ai-field-toggle="identity"', $view);
        $this->assertStringContainsString('data-ai-field-toggle="tracking"', $view);
        $this->assertStringContainsString('data-ai-field-toggle="description"', $view);
        $this->assertStringContainsString('data-asset-ai-select-all', $view);
        $this->assertStringContainsString('Gunakan yang dipilih', $view);
        $this->assertStringContainsString('Foto belum sesuai untuk bantuan AI', $view);

        $this->assertStringContainsString("selected.has('identity')", $javascript);
        $this->assertStringContainsString("selected.has('tracking')", $javascript);
        $this->assertStringContainsString("selected.has('description')", $javascript);
        $this->assertStringContainsString('assetAiAvailableFields', $javascript);

        $this->assertStringContainsString('Tangkapan layar Zoom/WhatsApp/browser adalah unsupported', $service);
        $this->assertStringContainsString('bukan caption gambar', $service);
        $this->assertStringContainsString("'eligible' => false", $service);
    }
}
