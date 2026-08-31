<?php

namespace Tests\Feature;

use App\Models\Question;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiContextualHelpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function question_help_is_static_and_no_longer_has_a_citizen_ai_endpoint(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $view = file_get_contents(resource_path('views/user/assets/assessment.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $controller = file_get_contents(app_path('Http/Controllers/AiHelpController.php'));

        $this->assertStringNotContainsString("/barang/{asset}/ai-help", $routes);
        $this->assertStringNotContainsString("assets.ai-help", $routes);
        $this->assertStringContainsString('data-help-text', $view);
        $this->assertStringContainsString('Bantuan pertanyaan', $view);
        $this->assertStringContainsString('questionBasicHelp', $javascript);
        $this->assertStringContainsString('openQuestionHelpModal', $javascript);
        $this->assertStringNotContainsString('contextualHelp(', $controller);
    }

    #[Test]
    public function every_seeded_question_keeps_readable_basic_help_without_ai(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertGreaterThan(0, Question::count());
        Question::query()->each(function (Question $question): void {
            $this->assertNotSame('', trim((string) $question->help_text), "Help text kosong untuk {$question->code}");
        });
    }
}
