<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1028UxRegressionTest extends TestCase
{
    #[Test]
    public function long_catalog_help_maps_admin_and_ai_rendering_have_the_new_ux_hooks(): void
    {
        $assetCreate = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $assessment = file_get_contents(resource_path('views/user/assets/assessment.blade.php'));
        $handover = file_get_contents(resource_path('views/user/handovers/match.blade.php'));
        $handoverText = preg_replace('/\s+/u', ' ', $handover);
        $partnerChoices = file_get_contents(resource_path('views/user/handovers/partners.blade.php'));
        $admin = file_get_contents(resource_path('views/admin/master/index.blade.php'));
        $partner = file_get_contents(resource_path('views/partner/assets/show.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-searchable="true"', $assetCreate);
        $this->assertStringContainsString('Cari jenis elektronik', $assetCreate);
        $this->assertStringContainsString('Bantuan pertanyaan', $assessment);
        $this->assertStringContainsString('Buat deskripsi dengan AI', $assessment);
        $this->assertStringNotContainsString('Tanya AI', $assessment);
        $this->assertStringNotContainsString('Penjelasan tambahan AI', $assessment);
        $this->assertStringContainsString('Gunakan link Google Maps', $handoverText);
        $this->assertStringContainsString('Saya setuju dengan penyerahan final', $partnerChoices);
        $this->assertStringContainsString('data-master-tab="groups"', $admin);
        $this->assertStringContainsString('data-master-tab="partner"', $admin);
        $this->assertStringContainsString('data-question-editor', $admin);
        $this->assertStringContainsString('partner-assessment-questions', $partner);
        $this->assertStringNotContainsString('value="RETURNED_TO_OWNER"', $partner);
        $this->assertStringContainsString('renderSafeMarkdown', $js);
        $this->assertStringContainsString('textContent', $js);
        $this->assertStringContainsString('sirkel-select-menu', $js);
    }
}
