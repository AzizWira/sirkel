<?php

namespace Tests\Feature;

use App\Services\RegionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V1063RegionLookupAndBulkStageRecoveryTest extends TestCase
{
    public function test_handover_submit_is_guarded_while_region_lookup_is_pending(): void
    {
        $multi = file_get_contents(resource_path('views/user/handovers/multi-form.blade.php'));
        $single = file_get_contents(resource_path('views/user/handovers/match.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-region-dependent-submit>Susun Rencana Mitra', $multi);
        $this->assertStringContainsString('data-region-dependent-submit>Cari Mitra yang Cocok', $single);
        $this->assertStringContainsString('const setRegionLookupPending = pending =>', $js);
        $this->assertStringContainsString('button.disabled = regionLookupPending', $js);
        $this->assertStringContainsString('clearRegionForNewPoint();', $js);
        $this->assertStringContainsString('setRegionLookupPending(true);', $js);
        $this->assertStringContainsString('setRegionLookupPending(false);', $js);
    }

    public function test_reverse_geocoding_can_resolve_region_from_display_name_when_address_fields_are_sparse(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'display_name' => 'Gunung Anyar Tambak, Kecamatan Gunung Anyar, Kota Surabaya, Jawa Timur',
                'address' => [
                    'city' => 'Surabaya',
                    'state' => 'Jawa Timur',
                ],
            ], 200),
        ]);

        $location = app(RegionService::class)->reverseGeocode(-7.3340, 112.7860);

        $this->assertNotNull($location);
        $this->assertSame('Gunung Anyar', $location['district']);
        $this->assertSame('Gunung Anyar Tambak', $location['village']);
    }

    public function test_stale_bulk_review_url_redirects_to_current_stage_instead_of_throwing_422(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BulkIntakeController.php'));

        $this->assertStringNotContainsString("abort_unless(\$session->status === IntakeSession::STATUS_DRAFT, 422, 'Sesi Bulk ini sudah masuk tahap berikutnya.')", $controller);
        $this->assertStringContainsString("IntakeSession::STATUS_QUESTIONNAIRE => redirect()->route('user.bulk.questionnaire', \$session)", $controller);
        $this->assertStringContainsString("IntakeSession::STATUS_REVIEW => redirect()->route('user.intake.review', \$session)", $controller);
    }
}
