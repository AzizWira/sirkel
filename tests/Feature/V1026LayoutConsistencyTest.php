<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1026LayoutConsistencyTest extends TestCase
{
    #[Test]
    public function recommendation_card_uses_its_own_readable_reason_block(): void
    {
        $view = file_get_contents(resource_path('views/user/handovers/partners.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('class="recommendation-reason"', $view);
        $this->assertStringContainsString('class="cluster partner-card-heading"', $view);
        $this->assertStringContainsString('class="partner-card-action"', $view);
        $this->assertStringNotContainsString('class="notice mb-16"><strong>Mengapa ini direkomendasikan?', $view);
        $this->assertMatchesRegularExpression('/\.recommendation-reason\s*\{/u', $css);
        $this->assertMatchesRegularExpression('/\.partner-card-heading\s+h3\s*\{\s*margin\s*:\s*0/u', $css);
    }

    #[Test]
    public function homepage_faq_uses_the_same_container_width_as_other_sections(): void
    {
        $home = file_get_contents(resource_path('views/public/home.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('class="stack faq-list"', $home);
        $this->assertMatchesRegularExpression('/\.faq-list\s*\{(?=[^}]*width\s*:\s*100%)(?=[^}]*max-width\s*:\s*none)[^}]*\}/u', $css);
        $this->assertDoesNotMatchRegularExpression('/\.faq-list\s*\{[^}]*max-width\s*:\s*900px/u', $css);
    }
}
