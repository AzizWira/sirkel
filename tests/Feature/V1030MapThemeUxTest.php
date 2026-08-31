<?php

namespace Tests\Feature;

use App\Services\MapLinkService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1030MapThemeUxTest extends TestCase
{
    #[Test]
    public function map_link_service_reads_common_google_maps_coordinate_formats(): void
    {
        $service = app(MapLinkService::class);

        foreach ([
            'https://www.google.com/maps/place/Test/@-7.2710826,112.7538405,17z' => [-7.2710826, 112.7538405],
            'https://www.google.com/maps/search/?api=1&query=-7.2810001,112.7810002' => [-7.2810001, 112.7810002],
            'https://maps.google.co.id/maps?q=-7.3012345,112.8012345' => [-7.3012345, 112.8012345],
            'https://www.google.com/maps/place/x/data=!3m1!4b1!4m6!3m5!1sabc!8m2!3d-7.2912345!4d112.7712345' => [-7.2912345, 112.7712345],
        ] as $url => [$lat, $lng]) {
            $resolved = $service->resolve($url);
            $this->assertNotNull($resolved, $url);
            $this->assertEqualsWithDelta($lat, $resolved['latitude'], 0.0000001);
            $this->assertEqualsWithDelta($lng, $resolved['longitude'], 0.0000001);
        }
    }

    #[Test]
    public function map_link_service_can_read_coordinates_embedded_in_google_share_html(): void
    {
        Http::fake([
            'https://maps.app.goo.gl/*' => Http::response(
                '<html><head><meta property="place:location:latitude" content="-7.2710826"><meta property="place:location:longitude" content="112.7538405"></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $resolved = app(MapLinkService::class)->resolve('https://maps.app.goo.gl/ExampleShortLink');

        $this->assertNotNull($resolved);
        $this->assertEqualsWithDelta(-7.2710826, $resolved['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(112.7538405, $resolved['longitude'], 0.0000001);
        $this->assertTrue($resolved['resolved']);
    }

    #[Test]
    public function theme_is_bootstrapped_before_vite_assets_to_prevent_light_flash(): void
    {
        foreach (['app.blade.php', 'auth.blade.php', 'public.blade.php'] as $layout) {
            $contents = file_get_contents(resource_path('views/layouts/'.$layout));
            preg_match('/<x-theme-bootstrap\s*\/>/', $contents, $themeMatch, PREG_OFFSET_CAPTURE);
            $themeAt = $themeMatch[0][1] ?? false;
            $viteAt = strpos($contents, '@vite');

            $this->assertNotFalse($themeAt, $layout);
            $this->assertNotFalse($viteAt, $layout);
            $this->assertLessThan($viteAt, $themeAt, $layout);
        }
    }

    #[Test]
    public function partner_location_and_request_views_expose_real_maps_links(): void
    {
        $onboarding = file_get_contents(resource_path('views/partner/onboarding/create.blade.php'));
        $request = file_get_contents(resource_path('views/partner/requests/show.blade.php'));

        $this->assertStringContainsString('data-map-link-picker', $onboarding);
        $this->assertStringContainsString('data-generated-map-link', $onboarding);
        $this->assertStringContainsString('data-readonly="true"', $request);
        $this->assertStringContainsString('Buka di Google Maps', $request);
    }

    #[Test]
    public function flow_guides_use_lines_instead_of_arrow_glyphs(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $request = file_get_contents(resource_path('views/partner/requests/show.blade.php'));

        $this->assertMatchesRegularExpression("/\.workflow\s+span:not\(:last-child\):after\s*\{\s*content\s*:\s*''/u", $css);
        $this->assertMatchesRegularExpression('/\.flow-guide-arrow\s*\{[^}]*width\s*:\s*34px\s*;[^}]*height\s*:\s*1px/u', $css);
        $this->assertStringNotContainsString('<div class="flow-guide-arrow">→</div>', $request);
    }
}
