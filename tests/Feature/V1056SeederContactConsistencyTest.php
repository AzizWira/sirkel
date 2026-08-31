<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1056SeederContactConsistencyTest extends TestCase
{
    public function test_demo_seeders_use_the_single_production_demo_contact_number(): void
    {
        $demo = file_get_contents(database_path('seeders/DemoSeeder.php'));
        $master = file_get_contents(database_path('seeders/MasterDataSeeder.php'));

        $this->assertStringContainsString("private const DEMO_CONTACT_NUMBER = '6289650484363';", $demo);
        $this->assertStringContainsString("'whatsapp' => self::DEMO_CONTACT_NUMBER", $demo);
        $this->assertStringContainsString("'phone' => \$user->whatsapp", $demo);
        $this->assertStringContainsString("['ai.topup_admin_whatsapp', '6289650484363', 'string', 'ai']", $master);

        $this->assertStringNotContainsString('628111111111', $demo.$master);
        $this->assertStringNotContainsString('628122222222', $demo.$master);
        $this->assertStringNotContainsString("'62813333'.str_pad", $demo.$master);
    }
}
