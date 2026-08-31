<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1021NaturalUxSeoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_home_has_search_metadata_structured_data_and_natural_product_copy(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Pengelolaan E-Waste &amp; Elektronik Sirkular Surabaya', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertSee('FAQPage')
            ->assertDontSee('&amp;amp;', false)
            ->assertDontSee('context()->has', false)
            ->assertSee('Elektronik tak terpakai masih punya jalan.')
            ->assertDontSee('Registrasi saja tidak dianggap dampak')
            ->assertDontSee('Yang dihitung adalah barang yang benar-benar diverifikasi');
    }

    #[Test]
    public function sitemap_lists_only_indexable_public_pages(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee(route('home'), false)
            ->assertSee(route('public.partners'), false)
            ->assertSee(route('public.education'), false)
            ->assertDontSee('/login')
            ->assertDontSee('/admin');
    }

    #[Test]
    public function authentication_pages_are_not_indexed(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    #[Test]
    public function public_passport_explicitly_uses_noindex(): void
    {
        $source = file_get_contents(resource_path('views/public/passport.blade.php'));

        $this->assertMatchesRegularExpression("/@section\(\s*['\"]robots['\"]\s*,\s*['\"]noindex,follow['\"]\s*\)/", $source);
    }

    #[Test]
    public function implementation_explanations_do_not_return_to_primary_views(): void
    {
        $files = [
            resource_path('views/components/flash.blade.php'),
            resource_path('views/user/dashboard.blade.php'),
            resource_path('views/admin/dashboard.blade.php'),
            resource_path('views/partner/dashboard.blade.php'),
            resource_path('views/user/assets/assessment.blade.php'),
            resource_path('views/user/handovers/match.blade.php'),
            resource_path('views/admin/master/index.blade.php'),
            resource_path('views/partner/assets/index.blade.php'),
        ];

        $combined = collect($files)->map(fn ($file) => file_get_contents($file))->implode("\n");

        foreach ([
            'Bagian yang bermasalah juga ditandai langsung pada form di bawah.',
            'Tidak ada pilihan default agar tujuan penyerahan tidak terpilih tanpa sengaja.',
            'Riwayat append-only',
            'Untuk testing/demo, Anda tidak wajib mengubah halaman ini.',
            'Alur kerja mitra:',
            'Perubahan di sini benar-benar digunakan oleh mesin rekomendasi.',
        ] as $copy) {
            $this->assertStringNotContainsString($copy, $combined);
        }
    }

    #[Test]
    public function private_app_layout_is_not_indexed(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $layout);
    }
}
