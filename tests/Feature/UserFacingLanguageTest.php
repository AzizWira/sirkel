<?php

namespace Tests\Feature;

use App\Support\SirkelUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserFacingLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unknown_email_on_forgot_password_uses_safe_indonesian_message(): void
    {
        RateLimiter::clear('forgot:127.0.0.1');

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'belum-terdaftar@example.test',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas(
            'success',
            'Jika email tersebut terdaftar, tautan reset password akan dikirim. Silakan periksa kotak masuk dan folder spam.'
        );
        $response->assertSessionMissing('warning');
    }

    #[Test]
    public function internal_ai_and_workflow_keys_have_friendly_labels(): void
    {
        $this->assertSame('Pengenalan Barang', SirkelUi::label('asset_intake'));
        $this->assertSame('Penjelasan Hasil Cek Kondisi', SirkelUi::label('assessment_explanation'));
        $this->assertSame('Ringkasan Pemeriksaan Mitra', SirkelUi::label('partner_assessment_narrative'));
        $this->assertSame('Perlu Pemeriksaan Perbaikan', SirkelUi::label('REPAIR_ASSESSMENT'));
        $this->assertSame('Material Dipulihkan', SirkelUi::label('RECOVERY_CONFIRMED'));
        $this->assertSame('Diserahkan / Dibuang dengan Aman', SirkelUi::label('safe_handover'));
        $this->assertSame('Nonaktif oleh Admin', SirkelUi::label('inactive'));
    }
}
