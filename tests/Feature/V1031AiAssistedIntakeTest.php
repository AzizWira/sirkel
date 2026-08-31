<?php

namespace Tests\Feature;

use App\Models\{DeviceCategory, DeviceGroup};
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1031AiAssistedIntakeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function photo_ai_is_explicit_opt_in_and_camera_upload_controls_are_present(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $controller = file_get_contents(app_path('Http/Controllers/AssetController.php'));

        $this->assertStringContainsString('data-asset-gallery', $view);
        $this->assertStringContainsString('data-asset-camera', $view);
        $this->assertStringContainsString('Proses dengan AI', $view);
        $this->assertStringContainsString('tidak dikirim ke AI', $view);
        $this->assertStringContainsString("aiButton.addEventListener('click'", $javascript);
        $this->assertStringNotContainsString("app(AiService::class)->intake(\$asset", $controller);
        $this->assertStringContainsString('draftIntake(', $controller);
    }

    #[Test]
    public function draft_ai_suggestion_only_returns_form_assistance_fields_and_respects_batch_capability(): void
    {
        config()->set('sirkel.ai.api_key', 'test-key');

        $group = DeviceGroup::create([
            'code' => 'small-home',
            'name' => 'Peralatan Rumah Tangga Kecil',
            'sort_order' => 1,
            'active' => true,
        ]);
        $category = DeviceCategory::create([
            'device_group_id' => $group->id,
            'code' => 'charger-cable',
            'name' => 'Kabel Charger',
            'supports_batch' => true,
            'special_handling_possible' => false,
            'active' => true,
            'sort_order' => 1,
        ])->load('group');

        Http::fake([
            '*' => Http::response([
                'output_text' => json_encode([
                    'eligibility_status' => 'supported',
                    'eligibility_reason' => 'Terlihat kabel elektronik fisik.',
                    'detected_name' => 'Kabel Charger USB-C',
                    'category_code' => 'charger-cable',
                    'custom_item_name' => null,
                    'visible_quantity' => 3,
                    'same_item_group' => true,
                    'description' => 'Terlihat tiga kabel charger dengan lapisan luar yang tampak aus.',
                    'confidence' => 0.91,
                ], JSON_UNESCAPED_UNICODE),
                'usage' => [],
            ], 200),
        ]);

        $photo = UploadedFile::fake()->createWithContent('foto.jpg', 'fake-jpeg-content');
        $suggestion = app(AiService::class)->draftIntake([$photo], collect([$category]));

        $this->assertNotNull($suggestion);
        $this->assertSame($category->id, $suggestion['category_id']);
        $this->assertSame('charger-cable', $suggestion['category_code']);
        $this->assertSame('batch', $suggestion['tracking_type']);
        $this->assertSame(3, $suggestion['visible_quantity']);
        $this->assertSame('Terlihat tiga kabel charger dengan lapisan luar yang tampak aus.', $suggestion['description']);
        $this->assertArrayNotHasKey('brand', $suggestion);
        $this->assertArrayNotHasKey('model_name', $suggestion);
        $this->assertArrayNotHasKey('final_path', $suggestion);
    }
}
