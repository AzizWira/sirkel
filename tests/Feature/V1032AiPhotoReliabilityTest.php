<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1032AiPhotoReliabilityTest extends TestCase
{
    #[Test]
    public function photo_intake_is_first_and_ai_failures_are_explained(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AssetController.php'));
        $service = file_get_contents(app_path('Services/AiService.php'));
        $settings = file_get_contents(resource_path('views/admin/settings/edit.blade.php'));

        $photoPosition = strpos($view, 'Foto satu jenis barang * — 1–3 foto');
        $categoryPosition = strpos($view, 'Jenis perangkat *');
        $this->assertNotFalse($photoPosition);
        $this->assertNotFalse($categoryPosition);
        $this->assertLessThan($categoryPosition, $photoPosition);

        $this->assertStringContainsString('userFacingFailureMessage(', $controller);
        $this->assertStringNotContainsString('lastFailureMessage()', $controller);
        $this->assertStringContainsString('extractResponseText', $service);
        $this->assertStringContainsString("'provider_auth'", $service);
        $this->assertStringContainsString("'budget_reached'", $service);
        $this->assertStringContainsString('Status AI', $settings);
        $this->assertStringContainsString('hanya mengatur detail gambar', $settings);
    }
}
