<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1044IntakeUxContractTest extends TestCase
{
    #[Test]
    public function cart_bulk_and_topup_contracts_are_visible_in_final_ui(): void
    {
        $cart = file_get_contents(resource_path('views/user/cart/index.blade.php'));
        $bulkCreate = file_get_contents(resource_path('views/user/bulk/create.blade.php'));
        $bulkEdit = file_get_contents(resource_path('views/user/bulk/edit.blade.php'));
        $bulkQuestions = file_get_contents(resource_path('views/user/bulk/questionnaire.blade.php'));
        $quota = file_get_contents(resource_path('views/user/ai-quota/index.blade.php'));
        $settings = file_get_contents(resource_path('views/admin/settings/edit.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BulkIntakeController.php'));

        $this->assertStringContainsString('Keranjang tidak memiliki batas jumlah', $cart);
        $this->assertStringContainsString('0</strong>/3 dipilih', $cart);
        $this->assertStringContainsString('Maksimal 5', $bulkCreate);
        $this->assertStringContainsString('Tambah Barang Manual', $bulkEdit);
        $this->assertStringContainsString('maksimal 15 pertanyaan untuk seluruh sesi', $bulkCreate);
        $this->assertStringContainsString('Pertanyaan kondisi untuk semua barang', $bulkQuestions);
        $this->assertStringContainsString('Jawab hanya berdasarkan kondisi yang benar-benar Anda ketahui.', $bulkQuestions);
        $this->assertStringContainsString('public const MAX_GROUPS = 5', $controller);
        $this->assertStringContainsString('public const MAX_QUESTIONS = 15', $controller);
        $this->assertStringContainsString('Tambahan Bulk AI', $quota);
        $this->assertStringContainsString('Harga 1 sesi Bulk AI', $settings);
    }
}
