<?php

namespace Tests\Feature;

use App\Services\RegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegionMasterDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bundled_surabaya_master_is_complete_for_current_forms(): void
    {
        $service = app(RegionService::class);

        $districts = $service->surabayaDistricts()->pluck('name');
        $this->assertCount(31, $districts);
        $this->assertContains('Pabean Cantian', $districts);
        $this->assertContains('Asem Rowo', $districts);

        $totalVillages = $districts->sum(fn (string $district) => $service->villages($district)->count());
        $this->assertSame(153, $totalVillages);
    }

    #[Test]
    public function pabean_cantian_and_other_previously_missing_districts_have_villages_without_api_sync(): void
    {
        $service = app(RegionService::class);

        $pabean = $service->villages('Pabean Cantian')->pluck('name')->all();
        $this->assertContains('Bongkaran', $pabean);
        $this->assertContains('Krembangan Utara', $pabean);
        $this->assertContains('Nyamplungan', $pabean);
        $this->assertContains('Tanjung Perak', $pabean);

        $gayungan = $service->villages('Gayungan')->pluck('name')->all();
        $this->assertContains('Dukuh Menanggal', $gayungan);
        $this->assertContains('Ketintang', $gayungan);
    }

    #[Test]
    public function legacy_region_names_are_normalized_before_new_saves(): void
    {
        $service = app(RegionService::class);

        $this->assertSame(
            ['district' => 'Asem Rowo', 'village' => 'Asem Rowo'],
            $service->normalizeLocation('Asemrowo', 'Asem Rowo')
        );

        $this->assertSame(
            ['district' => 'Rungkut', 'village' => 'Kalirungkut'],
            $service->normalizeLocation('Rungkut', 'Kali Rungkut')
        );

        $this->assertTrue($service->isValidSurabayaLocation('Pabean Cantian', 'Perak Timur'));
        $this->assertSame(
            ['district' => 'Pabean Cantian', 'village' => 'Tanjung Perak'],
            $service->normalizeLocation('Pabean Cantian', 'Perak Timur')
        );
    }
}
