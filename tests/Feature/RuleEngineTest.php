<?php
namespace Tests\Feature;

use App\Services\RuleEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RuleEngineTest extends TestCase
{
    #[Test]
    public function swollen_battery_is_routed_to_special_handling(): void
    {
        $result = app(RuleEngine::class)->evaluate([
            'power_status' => 'off',
            'damage_level' => 'severe',
            'battery_swollen' => 'yes',
        ]);
        $this->assertSame('SPECIAL_HANDLING', $result['path']);
        $this->assertSame('special_handling', app(RuleEngine::class)->capabilityFor($result['path']));
    }

    #[Test]
    public function ambiguous_device_goes_to_repair_capable_technical_assessment(): void
    {
        $result = app(RuleEngine::class)->evaluate([
            'power_status' => 'off',
            'damage_level' => 'unknown',
            'technician_result' => 'not_checked',
        ]);
        $this->assertSame('TECHNICAL_ASSESSMENT', $result['path']);
        $this->assertSame('repair', app(RuleEngine::class)->capabilityFor($result['path']));
    }
}
