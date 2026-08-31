<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1057PwaInstallabilityTest extends TestCase
{
    public function test_all_primary_layouts_expose_installable_app_metadata_and_icons(): void
    {
        foreach (['app', 'public', 'auth'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString("asset('site.webmanifest')", $source);
            $this->assertStringContainsString("asset('brand/favicon-32.png')", $source);
            $this->assertStringContainsString("asset('brand/apple-touch-icon.png')", $source);
            $this->assertStringContainsString('mobile-web-app-capable', $source);
            $this->assertStringContainsString('apple-mobile-web-app-capable', $source);
        }
    }

    public function test_manifest_has_standalone_scope_and_required_app_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('SIRKEL', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        $icons = collect($manifest['icons']);
        $this->assertTrue($icons->contains(fn ($icon) => ($icon['sizes'] ?? null) === '192x192' && ($icon['purpose'] ?? null) === 'any'));
        $this->assertTrue($icons->contains(fn ($icon) => ($icon['sizes'] ?? null) === '512x512' && ($icon['purpose'] ?? null) === 'any'));
        $this->assertTrue($icons->contains(fn ($icon) => ($icon['sizes'] ?? null) === '512x512' && ($icon['purpose'] ?? null) === 'maskable'));

        foreach (['favicon-32.png', 'apple-touch-icon.png', 'pwa-192.png', 'pwa-512.png', 'pwa-maskable-512.png'] as $file) {
            $this->assertFileExists(public_path("brand/{$file}"));
        }
    }

    public function test_service_worker_and_install_prompt_are_registered_without_caching_private_navigation(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $worker = file_get_contents(public_path('sw.js'));
        $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $publicLayout = file_get_contents(resource_path('views/layouts/public.blade.php'));

        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js', { scope: '/' })", $js);
        $this->assertStringContainsString("window.addEventListener('beforeinstallprompt'", $js);
        $this->assertStringContainsString('[data-pwa-install]', $js);
        $this->assertStringContainsString('data-pwa-install', $appLayout);
        $this->assertStringContainsString('data-pwa-install', $publicLayout);

        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("'/build/'", $worker);
        $this->assertStringContainsString("'/brand/'", $worker);
    }
}
