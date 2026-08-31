<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1035ProductCopyAndAiOptInTest extends TestCase
{
    #[Test]
    public function citizen_and_partner_flows_do_not_run_hidden_ai_or_expose_internal_copy(): void
    {
        $assetController = file_get_contents(app_path('Http/Controllers/AssetController.php'));
        $partnerController = file_get_contents(app_path('Http/Controllers/PartnerAssetController.php'));
        $assessmentView = file_get_contents(resource_path('views/user/assets/assessment.blade.php'));
        $assetView = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $chooseAccess = file_get_contents(resource_path('views/auth/choose-access.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $aiService = file_get_contents(app_path('Services/AiService.php'));

        $this->assertStringNotContainsString('->explain($asset, $answers, $rule)', $assetController);
        $this->assertStringNotContainsString('partnerNarrative($asset, $answers, $decision)', $partnerController);
        $this->assertStringNotContainsString('Bantuan Identifikasi', $assessmentView);
        $this->assertStringNotContainsString('tidak mengirim form otomatis', $assessmentView);
        $this->assertStringNotContainsString('form belum dikirim', $javascript);
        $this->assertStringNotContainsString('sesi login sekarang', $chooseAccess);
        $this->assertStringNotContainsString('Tidak ada data yang diubah atau dikirim otomatis', $assetView);

        $this->assertStringContainsString('userFacingFailureMessage', $aiService);
        $this->assertStringContainsString('Abaikan tujuan penyerahan/keinginan warga', $aiService);
        $this->assertStringContainsString('Tidak ada gejala tambahan yang perlu dicatat.', $aiService);
    }
}
