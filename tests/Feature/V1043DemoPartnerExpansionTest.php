<?php

namespace Tests\Feature;

use App\Models\{PartnerCapabilityModel, PartnerProfile};
use Database\Seeders\{DemoSeeder, MasterDataSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1043DemoPartnerExpansionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fresh_demo_seed_has_broader_surabaya_partner_coverage(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(25, PartnerProfile::count());
        $this->assertNotNull(PartnerProfile::where('business_name', 'Gunung Anyar Reuse Point')->first());
        $this->assertNotNull(PartnerProfile::where('business_name', 'Recovery Material Kenjeran')->first());
        $this->assertNotNull(PartnerProfile::where('business_name', 'Tandes Elektronik Care')->first());
        $this->assertGreaterThanOrEqual(7, PartnerCapabilityModel::where('capability', 'repair')->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(5, PartnerCapabilityModel::where('capability', 'recovery')->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(5, PartnerCapabilityModel::where('capability', 'reuse_donation')->where('status', 'approved')->count());
    }
}
