<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1037MatchingAssistanceTest extends TestCase
{
    #[Test]
    public function matching_help_has_admin_to_partner_follow_up_flow(): void
    {
        $issueController = file_get_contents(app_path('Http/Controllers/IssueController.php'));
        $adminController = file_get_contents(app_path('Http/Controllers/AdminIssueController.php'));
        $partnerController = file_get_contents(app_path('Http/Controllers/PartnerRequestController.php'));
        $matching = file_get_contents(app_path('Services/PartnerMatchingService.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $userView = file_get_contents(resource_path('views/user/handovers/partners.blade.php'));
        $adminView = file_get_contents(resource_path('views/admin/issues/index.blade.php'));

        $this->assertStringContainsString('matching_help_authorization', $userView);
        $this->assertStringContainsString("'context_json' => \$context", $issueController);
        $this->assertStringContainsString("Route::post('/laporan/{issue}/tawarkan-mitra'", $routes);
        $this->assertStringContainsString('public function offerPartner', $adminController);
        $this->assertStringContainsString('assistanceCandidates', $matching);
        $this->assertStringContainsString('supportsAssistedRequest', $matching);
        $this->assertStringContainsString('Tawarkan ke Mitra', $adminView);
        $this->assertStringContainsString("where('category', 'matching_help')", $partnerController);
        $this->assertStringContainsString("'status' => 'resolved'", $partnerController);
    }
}
