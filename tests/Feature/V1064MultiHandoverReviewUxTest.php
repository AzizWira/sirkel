<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1064MultiHandoverReviewUxTest extends TestCase
{
    public function test_multi_partner_heading_has_dedicated_responsive_layout(): void
    {
        $view = file_get_contents(resource_path('views/user/handovers/multi-partners.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('page-head multi-partner-page-head', $view);
        $this->assertStringContainsString('multi-partner-page-copy', $view);
        $this->assertStringContainsString('multi-partner-change-button', $view);
        $this->assertStringContainsString('.multi-partner-page-head{', $css);
        $this->assertStringContainsString('grid-template-columns:minmax(0,1fr) auto', $css);
        $this->assertStringContainsString('.multi-partner-change-button{align-self:start;white-space:nowrap', $css);
    }

    public function test_single_actionable_standard_item_does_not_offer_multi_handover_button(): void
    {
        $review = file_get_contents(resource_path('views/user/intake/review.blade.php'));

        $this->assertStringContainsString('$actionableCount > 1', $review);
        $this->assertStringContainsString('>Atur Penyerahan Semua</a>', $review);
        $this->assertStringNotContainsString("\$actionableCount === 1 ? 'Atur Penyerahan'", $review);
        $this->assertStringContainsString("route('user.handovers.match.form', \$asset)", $review);
        $this->assertStringContainsString('>Atur Barang Ini Saja</a>', $review);
    }

    public function test_multi_remains_standard_cart_processing_and_is_not_bulk_ai(): void
    {
        $cart = file_get_contents(resource_path('views/user/cart/index.blade.php'));
        $multi = file_get_contents(resource_path('views/user/handovers/multi-partners.blade.php'));
        $bulk = file_get_contents(resource_path('views/user/bulk/create.blade.php'));

        $this->assertStringContainsString('data-cart-process-form', $cart);
        $this->assertStringContainsString('Rencana Mitra Multi-Barang', $multi);
        $this->assertStringNotContainsString('Bulk AI · PRO', $multi);
        $this->assertStringContainsString('Bulk AI', $bulk);
    }
}
