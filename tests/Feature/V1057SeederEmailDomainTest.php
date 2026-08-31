<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1057SeederEmailDomainTest extends TestCase
{
    public function test_all_literal_emails_in_seeders_use_the_production_demo_domain(): void
    {
        $files = glob(database_path('seeders/*.php')) ?: [];
        $this->assertNotEmpty($files);

        $emails = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertStringNotContainsString('@sirkel.test', $source, basename($file).' masih memakai domain demo lama.');

            preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $source, $matches);
            array_push($emails, ...($matches[0] ?? []));
        }

        $this->assertNotEmpty($emails);
        foreach (array_unique($emails) as $email) {
            $this->assertStringEndsWith('@sirkel.awicode.com', $email, "Email seeder {$email} belum memakai sirkel.awicode.com.");
        }
    }

    public function test_demo_partner_lookup_uses_the_same_seeded_email_domain(): void
    {
        $source = file_get_contents(database_path('seeders/DemoSeeder.php'));

        $this->assertStringContainsString("'admin@sirkel.awicode.com'", $source);
        $this->assertStringContainsString("'warga@sirkel.awicode.com'", $source);
        $this->assertStringContainsString("'repair@sirkel.awicode.com'", $source);
        $this->assertStringContainsString("\$partners['repair@sirkel.awicode.com']", $source);
    }
}
