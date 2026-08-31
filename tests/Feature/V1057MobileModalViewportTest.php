<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1057MobileModalViewportTest extends TestCase
{
    public function test_mobile_asset_modal_stays_above_navigation_and_inside_dynamic_viewport(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertMatchesRegularExpression('/\\.modal-backdrop\\{[^}]*z-index:1600[^}]*\\}/s', $css);
        $this->assertStringContainsString('.asset-media-modal{padding:max(12px,env(safe-area-inset-top)) 12px max(12px,env(safe-area-inset-bottom));place-items:center}', $css);
        $this->assertStringContainsString('max-height:calc(100dvh - 24px - env(safe-area-inset-top) - env(safe-area-inset-bottom))', $css);
        $this->assertStringContainsString('.asset-modal-actions{position:sticky;bottom:-14px', $css);
        $this->assertStringNotContainsString('.asset-media-modal{padding:0;place-items:end center}', $css);

        $this->assertStringContainsString('function setAssetMediaModalOpen(modal, open)', $js);
        $this->assertStringContainsString("document.body.classList.toggle(\n            'asset-modal-open'", $js);
    }

    public function test_mobile_bottom_spacing_is_scoped_to_authenticated_app_and_viewport_is_safe_area_aware(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $app = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $public = file_get_contents(resource_path('views/layouts/public.blade.php'));
        $auth = file_get_contents(resource_path('views/layouts/auth.blade.php'));

        $this->assertStringContainsString('<body class="app-body">', $app);
        $this->assertStringContainsString('.app-body{padding-bottom:calc(58px + env(safe-area-inset-bottom))}', $css);
        $this->assertStringNotContainsString('body{padding-bottom:68px}', $css);
        $this->assertStringContainsString('height:calc(58px + env(safe-area-inset-bottom))', $css);
        $this->assertStringContainsString('.footer{padding:24px 0}', $css);

        foreach ([$app, $public, $auth] as $layout) {
            $this->assertStringContainsString('width=device-width,initial-scale=1,viewport-fit=cover', $layout);
        }
    }

    public function test_mobile_typography_and_controls_use_compact_responsive_scale(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('font-size:clamp(14px,3.7vw,15px)', $css);
        $this->assertStringContainsString('h1{font-size:clamp(34px,10vw,42px)}', $css);
        $this->assertStringContainsString('.page-head h2{font-size:clamp(22px,6.5vw,28px)}', $css);
        $this->assertStringContainsString('.btn{min-height:40px;padding:8px 12px;font-size:13px}', $css);
        $this->assertStringContainsString('.input,.select,.textarea{min-height:44px;padding:10px 12px;font-size:14px}', $css);
    }
}
