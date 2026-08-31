<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1043AiQuotaUxTest extends TestCase
{
    #[Test]
    public function quota_and_topup_ux_exposes_manual_whatsapp_flow_without_admin_route_in_message_template(): void
    {
        $userView = file_get_contents(resource_path('views/user/ai-quota/index.blade.php'));
        $createView = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $assessmentView = file_get_contents(resource_path('views/user/assets/assessment.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AiQuotaController.php'));
        $settings = file_get_contents(resource_path('views/admin/settings/edit.blade.php'));

        $this->assertStringContainsString('Dilarang mengubah format teks', $userView);
        $this->assertStringContainsString('DILARANG mengubah', $controller);
        $this->assertStringContainsString("route('ai-topups.resolve'", $controller);
        $this->assertStringNotContainsString("route('admin.ai-quota.show'", $controller);
        $this->assertStringContainsString('data-ai-quota-remaining', $createView);
        $this->assertStringContainsString('data-ai-description-quota', $assessmentView);
        $this->assertStringContainsString('Harga 1× Pengenalan Barang', $settings);
        $this->assertStringContainsString('WhatsApp Admin untuk Top Up', $settings);
    }
}
