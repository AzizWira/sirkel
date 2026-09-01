<?php

namespace Tests\Feature;

use Tests\TestCase;

class V1065PhotoPreviewAndThemeSyncTest extends TestCase
{
    public function test_camera_and_upload_previews_show_the_entire_frame_used_for_ai(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $assetView = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $bulkView = file_get_contents(resource_path('views/user/bulk/create.blade.php'));
        $ai = file_get_contents(app_path('Services/AiService.php'));

        $this->assertStringContainsString('.asset-camera-stage video{width:100%;height:100%;object-fit:contain}', $css);
        $this->assertStringContainsString('.asset-photo-preview-card img{display:block;width:100%;aspect-ratio:4/3;object-fit:contain', $css);
        $this->assertStringContainsString('.bulk-photo-preview-card>img{width:100%;aspect-ratio:4/3;object-fit:contain', $css);
        $this->assertStringContainsString('Kamera menampilkan seluruh frame tanpa crop.', $assetView);
        $this->assertStringContainsString('Kamera menampilkan seluruh frame tanpa crop.', $bulkView);

        // Optimasi AI boleh resize, tetapi tetap memakai keseluruhan frame/aspect ratio sumber.
        $this->assertStringContainsString('imagecopyresampled(', $ai);
        $this->assertStringContainsString('(int) $info[0]', $ai);
        $this->assertStringContainsString('(int) $info[1]', $ai);
    }

    public function test_uploaded_and_saved_photos_can_open_a_full_image_preview(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('function openSirkelImagePreview(', $js);
        $this->assertStringContainsString('function makeSirkelImagePreviewable(', $js);
        $this->assertStringContainsString('data-image-preview-modal', $js);
        $this->assertStringContainsString("document.querySelectorAll('.asset-photos img, .bulk-photo-strip img')", $js);
        $this->assertStringContainsString('makeSirkelImagePreviewable(image, file);', $js);
        $this->assertStringContainsString('makeSirkelImagePreviewable(image, item.file);', $js);
        $this->assertStringContainsString('.image-preview-stage img{', $css);
        $this->assertStringContainsString('object-fit:contain', $css);
    }

    public function test_public_theme_toggle_uses_the_same_authenticated_preference_as_app_pages(): void
    {
        $publicLayout = file_get_contents(resource_path('views/layouts/public.blade.php'));
        $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('@auth<meta name="authenticated-user" content="{{ auth()->id() }}">@endauth', $publicLayout);
        $this->assertStringContainsString('meta name="authenticated-user" content="{{ auth()->id() }}"', $appLayout);
        $this->assertStringContainsString("axios.post('/theme', {theme: pref})", $js);
        $this->assertStringContainsString('sirkel-theme-user-', $js);
    }
}
