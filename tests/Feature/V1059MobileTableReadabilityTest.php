<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1059MobileTableReadabilityTest extends TestCase
{
    public function test_asset_table_keeps_readable_mobile_width_and_compacts_passport_code(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/index.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('mobile-table mobile-table-6 asset-list-table', $view);
        $this->assertStringContainsString('class="passport-table-cell"', $view);
        $this->assertStringContainsString('class="passport-table-code"', $view);
        $this->assertStringContainsString('.asset-list-table{min-width:760px}', $css);
        $this->assertStringContainsString('.asset-list-table .passport-table-code{font-size:10px', $css);
        $this->assertStringContainsString('width:82px;min-width:82px;max-width:82px', $css);
    }

    public function test_all_multi_column_data_tables_use_horizontal_mobile_width_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $views = [
            'views/user/assets/index.blade.php' => 'mobile-table-6',
            'views/user/ai-quota/index.blade.php' => 'mobile-table-4',
            'views/partner/requests/index.blade.php' => 'mobile-table-6',
            'views/admin/ai-quota/index.blade.php' => 'mobile-table-6',
            'views/admin/ai/index.blade.php' => 'mobile-table-5',
            'views/admin/audit/index.blade.php' => 'mobile-table-5',
            'views/admin/partners/index.blade.php' => 'mobile-table-7',
        ];

        foreach ($views as $relative => $class) {
            $view = file_get_contents(resource_path($relative));
            $this->assertStringContainsString('mobile-table '.$class, $view, "{$relative} belum memakai mobile table contract");
        }

        $this->assertStringContainsString('.table-wrap{overflow-x:auto;overflow-y:hidden', $css);
        $this->assertStringContainsString('.mobile-table-4{min-width:560px}', $css);
        $this->assertStringContainsString('.mobile-table-5{min-width:680px}', $css);
        $this->assertStringContainsString('.mobile-table-6{min-width:780px}', $css);
        $this->assertStringContainsString('.mobile-table-7{min-width:920px}', $css);
        $this->assertStringContainsString('-webkit-overflow-scrolling:touch', $css);
    }
}
