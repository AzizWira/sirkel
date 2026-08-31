<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1034AssessmentHelpUxTest extends TestCase
{
    #[Test]
    public function condition_help_uses_modal_and_ai_only_generates_notes_draft(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/assessment.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));
        $service = file_get_contents(app_path('Services/AiService.php'));

        $this->assertStringContainsString('data-question-help-modal', $view);
        $this->assertStringContainsString('Bantuan pertanyaan', $view);
        $this->assertStringContainsString('data-generate-condition-description', $view);
        $this->assertStringContainsString('Buat deskripsi dengan AI', $view);
        $this->assertStringNotContainsString('Tanya AI', $view);
        $this->assertStringNotContainsString('Penjelasan tambahan AI', $view);
        $this->assertStringContainsString('data-citizen-assessment-form', $view);
        $this->assertStringContainsString('bindCitizenAssessmentAi', $js);
        $this->assertStringContainsString('citizenConditionDescription', $service);
        $this->assertStringContainsString('Bantu rangkum kondisi penting agar lebih mudah dipahami mitra.', $view);
        $this->assertStringNotContainsString('tidak mengirim form otomatis', $view);
    }
}
