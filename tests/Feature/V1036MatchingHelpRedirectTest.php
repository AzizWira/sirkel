<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1036MatchingHelpRedirectTest extends TestCase
{
    #[Test]
    public function partner_search_uses_post_redirect_get_so_help_can_return_safely(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HandoverController.php'));
        $issueController = file_get_contents(app_path('Http/Controllers/IssueController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("Route::post('/barang/{asset}/cari-mitra'", $routes);
        $this->assertStringContainsString("Route::get('/barang/{asset}/cari-mitra'", $routes);
        $this->assertStringContainsString("name('handovers.partners')", $routes);
        $this->assertStringContainsString("\$request->session()->put(\$this->matchSessionKey(\$asset), \$data)", $controller);
        $this->assertStringContainsString("redirect()->route('user.handovers.partners', \$asset)", $controller);
        $this->assertStringContainsString("public function partners(Request \$request, Asset \$asset)", $controller);
        $this->assertStringContainsString("return back()->with('success', \$message);", $issueController);
    }
}
