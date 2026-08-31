<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1038AssistedMatchingRefreshGuardTest extends TestCase
{
    public function test_matching_get_pages_redirect_to_asset_detail_when_handover_is_already_active(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HandoverController.php'));
        $assetView = file_get_contents(resource_path('views/user/assets/show.blade.php'));

        $this->assertStringContainsString('handoverPageRedirect($asset)', $controller);
        $this->assertStringContainsString('Barang ini sudah memiliki penyerahan yang sedang berjalan.', $controller);
        $this->assertStringContainsString("redirect()->route('user.assets.show', \$asset)", $controller);

        $this->assertStringContainsString('href="#penyerahan-aktif"', $assetView);
        $this->assertStringContainsString('id="{{ $requestIsActive ? \'penyerahan-aktif\' : \'penyerahan-terakhir\' }}"', $assetView);
    }
}
