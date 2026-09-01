<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1045HandoverDonationCameraUxTest extends TestCase
{
    #[Test]
    public function partner_selection_and_multi_item_review_expose_the_new_convenience_actions(): void
    {
        $single = file_get_contents(resource_path('views/user/handovers/partners.blade.php'));
        $multi = file_get_contents(resource_path('views/user/handovers/multi-partners.blade.php'));
        $review = file_get_contents(resource_path('views/user/intake/review.blade.php'));
        $reviewText = preg_replace('/\s+/u', ' ', $review);

        $this->assertStringContainsString('Hubungi Mitra', $single);
        $this->assertStringContainsString('Pilih Mitra Ini untuk Semua', $multi);
        $this->assertStringContainsString('Satu mitra dapat menerima semua', $multi);
        $this->assertStringContainsString('Atur Penyerahan Semua', $reviewText);
        $this->assertStringContainsString("route('user.intake.handover.form'", $review);
    }

    #[Test]
    public function bulk_review_only_asks_for_custom_name_when_category_requires_it_and_marks_optional_fields(): void
    {
        $view = file_get_contents(resource_path('views/user/bulk/edit.blade.php'));
        $service = file_get_contents(app_path('Services/AiService.php'));
        $viewText = preg_replace('/\s+/u', ' ', $view);

        $this->assertStringContainsString('data-custom=', $view);
        $this->assertStringContainsString('data-bulk-custom-name', $view);
        $this->assertStringContainsString('(Opsional)', $view);
        $this->assertStringContainsString('placeholder=', $view);
        $this->assertStringContainsString('Dua kulkas dapat dicatat sebagai “Kulkas ×2”', $viewText);
        $this->assertStringContainsString('Contoh dua kulkas tetap Kulkas quantity 2', $service);
    }

    #[Test]
    public function rejected_offer_ui_keeps_negotiation_separate_from_rejecting_the_partner(): void
    {
        $view = file_get_contents(resource_path('views/user/assets/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/HandoverController.php'));

        $this->assertStringContainsString('Minta Penawaran Baru', $view);
        $this->assertStringContainsString('Ganti Mitra', $view);
        $this->assertStringContainsString('Batalkan Penyerahan', $view);
        $this->assertStringContainsString("'action' => 'required|in:reoffer,change_partner,cancel'", $controller);
        $this->assertStringContainsString('Data cara penyerahan sebelumnya dipertahankan', $controller);
    }

    #[Test]
    public function donation_is_not_final_until_partner_records_photo_time_and_device_location(): void
    {
        $partnerController = file_get_contents(app_path('Http/Controllers/PartnerAssetController.php'));
        $proofController = file_get_contents(app_path('Http/Controllers/DonationProofController.php'));
        $partnerView = file_get_contents(resource_path('views/partner/assets/show.blade.php'));
        $userView = file_get_contents(resource_path('views/user/assets/show.blade.php'));
        $passport = file_get_contents(app_path('Http/Controllers/PassportController.php'));
        $partnerViewText = preg_replace('/\s+/u', ' ', $partnerView);

        $this->assertStringContainsString("'awaiting_donation_proof'", $partnerController);
        $this->assertStringContainsString("'final_path' => 'DONATED'", $proofController);
        $this->assertStringContainsString("'photo' => 'required|image", $proofController);
        $this->assertStringContainsString("'latitude' => 'required|numeric", $proofController);
        $this->assertStringContainsString("'longitude' => 'required|numeric", $proofController);
        $this->assertStringContainsString('Bukti Donasi', $partnerView);
        $this->assertStringContainsString('Ambil Lokasi Perangkat', $partnerViewText);
        $this->assertStringContainsString('Individu (identitas disembunyikan)', $userView);
        $this->assertStringContainsString("'DONATION_PROOF_RECORDED'", $passport);
    }

    #[Test]
    public function every_current_image_upload_flow_has_a_camera_path(): void
    {
        $assetCreate = file_get_contents(resource_path('views/user/assets/create.blade.php'));
        $bulkCreate = file_get_contents(resource_path('views/user/bulk/create.blade.php'));
        $partnerOnboarding = file_get_contents(resource_path('views/partner/onboarding/create.blade.php'));
        $partnerAsset = file_get_contents(resource_path('views/partner/assets/show.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-asset-camera', $assetCreate);
        $this->assertStringContainsString('capture="environment"', $assetCreate);
        $this->assertStringContainsString('data-bulk-photo-picker', $bulkCreate);
        $this->assertStringContainsString('data-bulk-photo-gallery', $bulkCreate);
        $this->assertStringContainsString('data-bulk-photo-camera', $bulkCreate);
        $this->assertStringContainsString('data-bulk-camera-modal', $bulkCreate);
        $this->assertStringContainsString('navigator.mediaDevices?.getUserMedia', $js);
        $this->assertStringContainsString("setAttribute('capture', 'environment')", $js);
        $this->assertGreaterThanOrEqual(2, substr_count($partnerOnboarding, 'data-camera-file-picker'));
        $this->assertGreaterThanOrEqual(2, substr_count($partnerOnboarding, 'capture="environment"'));
        $this->assertStringContainsString('data-camera-file-picker', $partnerAsset);
        $this->assertStringContainsString('capture="environment"', $partnerAsset);
        $this->assertStringContainsString('function bindCameraFilePicker', $js);
    }
}
