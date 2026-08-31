<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1058MobileModalCameraReadmeTest extends TestCase
{
    public function test_all_mobile_modals_use_viewport_safe_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('.sirkel-modal-open{overflow:hidden;overscroll-behavior:none}', $css);
        $this->assertStringContainsString('.modal-backdrop .modal{width:min(100%,560px);max-height:calc(100dvh', $css);
        $this->assertStringContainsString('function syncSirkelModalBodyLock()', $js);
        $this->assertStringContainsString("document.querySelector('.modal-backdrop.show')", $js);
        $this->assertStringContainsString('new MutationObserver(syncSirkelModalBodyLock)', $js);
    }

    public function test_asset_camera_has_explicit_mobile_fallback_and_no_silent_capture_failure(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-asset-camera-native>Buka Kamera Ponsel', $view);
        $this->assertStringContainsString('data-asset-camera-capture disabled', $view);
        $this->assertStringContainsString('function openNativeAssetCamera(state)', $js);
        $this->assertStringContainsString('Harus terjadi langsung dari tap pengguna', $js);
        $this->assertStringContainsString('Gambar kamera belum siap.', $js);
        $this->assertStringContainsString('Izin/kamera browser tidak dapat digunakan.', $js);
        $this->assertStringContainsString('capture.name = main.name;', $js);
        $this->assertStringContainsString('main.disabled = true;', $js);
    }

    public function test_readme_puts_complete_demo_accounts_near_the_top(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $demoPosition = strpos($readme, '## Akun Demo / Pengujian');
        $summaryPosition = strpos($readme, '## Ringkasan');

        $this->assertNotFalse($demoPosition);
        $this->assertNotFalse($summaryPosition);
        $this->assertLessThan($summaryPosition, $demoPosition);
        $this->assertStringContainsString('pengujian lokal maupun deployment demo lomba', $readme);
        $seeder = file_get_contents(database_path('seeders/DemoSeeder.php'));
        preg_match_all('/[a-z0-9._-]+@sirkel\.awicode\.com/i', $seeder, $matches);
        $seededEmails = array_values(array_unique($matches[0]));
        sort($seededEmails);

        $this->assertCount(27, $seededEmails);
        foreach ($seededEmails as $email) {
            $this->assertStringContainsString($email, $readme, "README belum mencantumkan akun seeder {$email}");
        }
        $this->assertStringContainsString('6289650484363', $readme);
    }
}
