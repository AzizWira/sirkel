<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1050MultiHandoverMapContractTest extends TestCase
{
    #[Test]
    public function multi_handover_uses_the_same_map_picker_contract_as_the_stable_single_handover_flow(): void
    {
        $view = file_get_contents(resource_path('views/user/handovers/multi-form.blade.php'));

        $this->assertStringContainsString('data-map-id="multi-handover-map"', $view);
        $this->assertStringContainsString('data-lat-id="multi-handover-lat"', $view);
        $this->assertStringContainsString('data-lng-id="multi-handover-lng"', $view);
        $this->assertStringContainsString('data-location-source="map"', $view);
        $this->assertStringContainsString('data-location-source="link"', $view);
        $this->assertStringContainsString('id="multi-handover-map"', $view);
        $this->assertStringContainsString('data-picker-map', $view);
        $this->assertStringContainsString('data-auto-map', $view);
        $this->assertStringContainsString('data-generated-map-link', $view);
        $this->assertStringContainsString("getMyLocation('multi-handover-lat','multi-handover-lng','multi-handover-location-label','multi-handover-map')", $view);
    }

    #[Test]
    public function map_link_picker_keeps_backward_compatibility_for_legacy_location_attributes(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("root.querySelector('[data-location-map]')", $source);
        $this->assertStringContainsString("[data-location-source], [data-location-mode]", $source);
        $this->assertStringContainsString('generatedMapIdSequence', $source);
        $this->assertStringContainsString('initSirkelMap(mapId, latId, lngId', $source);
    }
}
