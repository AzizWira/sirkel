<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1060MobilePassportLegibilityTest extends TestCase
{
    public function test_asset_table_keeps_passport_code_legible_on_mobile(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $view = file_get_contents(resource_path('views/user/assets/index.blade.php'));

        $this->assertStringContainsString('.asset-list-table{min-width:820px}', $css);
        $this->assertStringContainsString('width:112px;min-width:112px;max-width:112px', $css);
        $this->assertStringContainsString('.asset-list-table .passport-table-code{font-size:12px', $css);
        $this->assertStringContainsString('.asset-list-table th,.asset-list-table td{font-size:14px}', $css);
        $this->assertStringNotContainsString('.asset-list-table .passport-table-code{font-size:10px', $css);
        $this->assertStringContainsString('passport-table-code', $view);
        $this->assertStringContainsString('mobile-table mobile-table-6 asset-list-table', $view);
    }
}
