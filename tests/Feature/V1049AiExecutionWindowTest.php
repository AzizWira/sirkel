<?php

namespace Tests\Feature;

use App\Services\AiService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class V1049AiExecutionWindowTest extends TestCase
{
    #[Test]
    public function ai_http_budget_is_not_longer_than_php_execution_window(): void
    {
        $originalLimit = (string) ini_get('max_execution_time');

        try {
            @ini_set('max_execution_time', '30');
            if (function_exists('set_time_limit')) {
                @set_time_limit(30);
            }

            config()->set('sirkel.ai.execution_timeout', 150);
            config()->set('sirkel.ai.execution_fallback_buffer', 8);

            $method = new ReflectionMethod(AiService::class, 'prepareExecutionBudget');
            $method->setAccessible(true);

            $result = $method->invoke(app(AiService::class), 20, 60, 2, [750]);
            $effective = (int) ini_get('max_execution_time');

            if ($effective === 0 || $effective >= 131) {
                // Environment mengizinkan execution window diperpanjang, sehingga
                // provider tetap mendapat timeout/retry normal.
                $this->assertSame([20, 60, 2], $result);
            } else {
                // Environment mengunci max_execution_time. SIRKEL harus memilih
                // satu request yang selesai sebelum hard PHP deadline.
                $this->assertSame(1, $result[2]);
                $this->assertLessThan($effective, $result[1]);
                $this->assertLessThanOrEqual($result[1], $result[0]);
            }
        } finally {
            @ini_set('max_execution_time', $originalLimit);
            if (function_exists('set_time_limit')) {
                @set_time_limit((int) $originalLimit);
            }
        }
    }

    #[Test]
    public function ai_execution_defaults_are_documented_in_config(): void
    {
        $this->assertSame(150, (int) config('sirkel.ai.execution_timeout'));
        $this->assertSame(8, (int) config('sirkel.ai.execution_fallback_buffer'));

        $source = file_get_contents(app_path('Services/AiService.php'));
        $this->assertStringContainsString('prepareExecutionBudget', $source);
        $this->assertStringContainsString('max_execution_time', $source);
    }
}
