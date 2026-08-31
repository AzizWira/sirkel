<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1048TestEnvironmentIsolationTest extends TestCase
{
    #[Test]
    public function automated_tests_use_in_memory_mail_transport(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('array', config('mail.default'));
    }
}
