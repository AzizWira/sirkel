<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1062BulkUxPolishTest extends TestCase
{
    public function test_bulk_review_tracks_dirty_groups_before_continue(): void
    {
        $view = file_get_contents(resource_path('views/user/bulk/edit.blade.php'));

        $this->assertStringContainsString('data-bulk-item-form', $view);
        $this->assertStringContainsString('data-bulk-save disabled', $view);
        $this->assertStringContainsString('data-bulk-continue-form', $view);
        $this->assertStringContainsString('Perubahan belum disimpan', $view);
        $this->assertStringContainsString('Simpan & Lanjutkan', $view);
        $this->assertStringContainsString('form.dataset.bulkDirty', $view);
    }

    public function test_bulk_questionnaire_surfaces_first_required_question_instead_of_silently_stopping(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('const revealFirstIncomplete = () =>', $js);
        $this->assertStringContainsString('Jawab pertanyaan ini sebelum melanjutkan.', $js);
        $this->assertStringContainsString("question.scrollIntoView({behavior: 'smooth', block: 'center'})", $js);
        $this->assertStringContainsString('.bulk-question.has-error', $css);
    }

    public function test_review_card_header_is_not_squeezed_by_status_badge(): void
    {
        $view = file_get_contents(resource_path('views/user/intake/review.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('review-item-head', $view);
        $this->assertStringContainsString('review-item-status', $view);
        $this->assertStringContainsString('.review-item-head{display:grid;gap:8px', $css);
        $this->assertStringContainsString('.review-item-status{justify-self:start', $css);
    }

    public function test_home_flow_markers_and_footer_use_consistent_public_copy(): void
    {
        $home = file_get_contents(resource_path('views/public/home.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/public.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('Mulai dari Gunung Anyar', $home);
        $this->assertStringNotContainsString('Mulai dari Gunung Anyar', $layout);
        $this->assertStringContainsString('flex:0 0 32px', $css);
        $this->assertStringContainsString('footer-grid', $layout);
    }
}
