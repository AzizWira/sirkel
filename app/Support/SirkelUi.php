<?php

namespace App\Support;

use Illuminate\Support\Str;

class SirkelUi
{
    private const LABELS = [
        // AI features
        'asset_intake' => 'Pengenalan Barang',
        'bulk_ai' => 'Bulk AI',
        'bulk_ai_detect' => 'Bulk AI — Pengenalan Banyak Barang',
        'bulk_ai_questionnaire' => 'Bulk AI — Pertanyaan Adaptif',
        'assessment_explanation' => 'Penjelasan Hasil Cek Kondisi',
        'citizen_condition_description' => 'Penyusunan Catatan Kondisi',
        'partner_assessment_narrative' => 'Ringkasan Pemeriksaan Mitra',
        'contextual_help' => 'Bantuan Pengisian',
        'admin_impact_narrative' => 'Ringkasan Dampak',
        'data_quality' => 'Pemeriksaan Kualitas Data',

        // Partner capabilities
        'collection' => 'Pengumpulan',
        'pickup' => 'Penjemputan',
        'repair' => 'Perbaikan',
        'reuse_donation' => 'Guna Ulang & Donasi',
        'recovery' => 'Pemulihan Material',
        'special_handling' => 'Penanganan Khusus',

        // Preliminary / final circular paths
        'REUSE' => 'Layak Guna Ulang',
        'DONATION' => 'Layak Donasi',
        'REPAIR_ASSESSMENT' => 'Perlu Pemeriksaan Perbaikan',
        'TECHNICAL_ASSESSMENT' => 'Perlu Pemeriksaan Teknis',
        'PARTS_RECOVERY' => 'Pemulihan Komponen',
        'SPECIAL_HANDLING' => 'Penanganan Khusus',
        'RECOVERY' => 'Pemulihan Material',
        'REUSED' => 'Disalurkan untuk Digunakan Kembali',
        'REPAIRED' => 'Dipulihkan sebagai Perangkat Utuh',
        'DONATED' => 'Didonasikan',
        'DONATION_READY' => 'Siap Disalurkan untuk Donasi',
        'PARTS_RECOVERED' => 'Komponen Dipulihkan',
        'RECEIVED_BY_RECOVERY_PARTNER' => 'Diterima Mitra Pemulihan (Belum Hasil Akhir)',
        'RECOVERY_CONFIRMED' => 'Material Dipulihkan',
        'SPECIAL_HANDLING_COMPLETED' => 'Penanganan Khusus Terkonfirmasi',
        'RETURNED_TO_OWNER' => 'Dikembalikan ke Pemilik',
        'UNVERIFIED_FINAL_TREATMENT' => 'Ditutup Tanpa Hasil Akhir Terverifikasi',
        'SPLIT_TO_SUB_BATCHES' => 'Dipisahkan Menjadi Beberapa Kelompok',

        // Asset / request / offer / moderation statuses
        'cart' => 'Di Keranjang',
        'bulk_draft' => 'Draft Bulk AI',
        'draft' => 'Draft',
        'questionnaire' => 'Mengisi Kondisi',
        'review' => 'Siap Ditinjau',
        'carted' => 'Disimpan ke Keranjang',
        'registered' => 'Terdaftar',
        'assessed' => 'Sudah Dicek',
        'matching' => 'Mencari Mitra',
        'requested' => 'Menunggu Tanggapan',
        'pending' => 'Menunggu',
        'partner_accepted' => 'Diterima Mitra',
        'offered' => 'Ada Penawaran',
        'offer_accepted' => 'Penawaran Diterima',
        'offer_rejected' => 'Penawaran Ditolak',
        'scheduled' => 'Terjadwal',
        'received' => 'Barang Diterima Mitra',
        'in_processing' => 'Sedang Ditangani Mitra',
        'awaiting_donation_proof' => 'Menunggu Bukti Donasi',
        'CONTINUE_HANDLING' => 'Penanganan Dilanjutkan oleh Mitra Saat Ini',
        'transfer_pending' => 'Menunggu Konfirmasi Mitra Tujuan',
        'transferred' => 'Dialihkan ke Mitra Lain',
        'completed' => 'Penyerahan Selesai',
        'needs_transfer' => 'Perlu Dialihkan',
        'special_handling_completed' => 'Penanganan Khusus Terkonfirmasi',
        'TRANSFER_REQUIRED' => 'Perlu Dialihkan ke Mitra Lain',
        'TRANSFER_REPAIR' => 'Lanjutkan ke Mitra Perbaikan',
        'TRANSFER_REUSE_DONATION' => 'Lanjutkan ke Mitra Guna Ulang & Donasi',
        'TRANSFER_RECOVERY' => 'Lanjutkan ke Mitra Pemulihan Material',
        'TRANSFER_SPECIAL_HANDLING' => 'Lanjutkan ke Mitra Penanganan Khusus',
        'closed' => 'Ditutup',
        'declined' => 'Ditolak Mitra',
        'cancelled_by_user' => 'Dibatalkan Warga',
        'cancelled_by_partner' => 'Dibatalkan Mitra',
        'expired' => 'Kedaluwarsa',
        'waiting_user' => 'Menunggu Warga',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
        'superseded' => 'Digantikan Penawaran Baru',
        'requested' => 'Diusulkan Warga',
        'proposed' => 'Diusulkan Mitra',
        'approved' => 'Disetujui',
        'open' => 'Terbuka',
        'in_review' => 'Sedang Ditinjau',
        'resolved' => 'Selesai Ditangani',
        'dismissed' => 'Ditutup Tanpa Tindakan',
        'success' => 'Berhasil',
        'failed' => 'Gagal',
        'fallback' => 'Menggunakan Cara Manual',

        // Handover / tracking
        'dropoff' => 'Antar ke Mitra',
        'sale' => 'Dengan Penawaran Nilai',
        'free_handover' => 'Penyerahan Tanpa Kompensasi',
        'donation' => 'Donasi',
        'individual' => 'Satuan',
        'batch' => 'Kelompok Barang',

        // Issue categories
        'partner_no_show' => 'Mitra Tidak Datang',
        'item_mismatch' => 'Kondisi / Barang Tidak Sesuai',
        'value_problem' => 'Masalah Nilai Akhir',
        'behavior' => 'Perilaku Tidak Sesuai',
        'no_update' => 'Tidak Ada Pembaruan',
        'matching_help' => 'Bantuan Pencarian Mitra',
        'other' => 'Lainnya',
        'user_unreachable' => 'Warga Tidak Dapat Dihubungi',

        // Question types / common admin values
        'single' => 'Pilihan Tunggal',
        'multi' => 'Pilihan Jamak',
        'text' => 'Isian Teks',
        'boolean' => 'Ya / Tidak',
        'normal' => 'Normal',
        'partial' => 'Sebagian Berfungsi',
        'off' => 'Tidak Menyala',
        'unknown' => 'Belum Diketahui',
        'none' => 'Tidak Ada Kerusakan Berarti',
        'minor' => 'Ringan',
        'moderate' => 'Sedang',
        'severe' => 'Berat',
        'yes' => 'Ya',
        'no' => 'Tidak',
        'repairable' => 'Masih Bisa Diperbaiki',
        'not_repairable' => 'Dinyatakan Tidak Layak Diperbaiki',
        'not_checked' => 'Belum Pernah Diperiksa',
        'reuse' => 'Dimanfaatkan Kembali jika Masih Memungkinkan',
        'donate' => 'Disalurkan / Didonasikan',
        'safe_handover' => 'Diserahkan / Dibuang dengan Aman',
        'recycle' => 'Pemulihan Material jika Sudah Tidak Layak',
        'unsure' => 'Bantu Rekomendasikan',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif oleh Admin',

        // Audit actions
        'issue.moderate' => 'Memperbarui Moderasi Laporan',
        'issue.matching_partner_offer' => 'Menawarkan Bantuan ke Mitra',
        'partner.review' => 'Meninjau Verifikasi Mitra',
        'partner.manage' => 'Memperbarui Layanan Mitra',
        'partner.status' => 'Mengubah Status Operasional Mitra',
        'master.group.save' => 'Menyimpan Kelompok Barang',
        'master.category.save' => 'Menyimpan Kategori Barang',
        'master.question.save' => 'Menyimpan Pertanyaan Pemeriksaan',
        'master.questionnaire.save' => 'Menyimpan Form Cek Kondisi',
        'master.rule.save' => 'Menyimpan Aturan Keputusan',
    ];

    private const RESOURCES = [
        'PartnerProfile' => 'Profil Mitra',
        'IssueReport' => 'Laporan Masalah',
        'DeviceCategory' => 'Kategori Barang',
        'QuestionnaireTemplate' => 'Form Cek Kondisi',
        'CircularRule' => 'Aturan Keputusan',
        'Asset' => 'Barang Elektronik',
        'HandoverRequest' => 'Permintaan Penyerahan',
        'Offer' => 'Penawaran',
    ];

    public static function label(mixed $value, string $fallback = '-'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $key = (string) $value;

        if (array_key_exists($key, self::LABELS)) {
            return self::LABELS[$key];
        }

        $upper = strtoupper($key);
        if (array_key_exists($upper, self::LABELS)) {
            return self::LABELS[$upper];
        }

        return Str::of($key)
            ->replace(['_', '-'], ' ')
            ->lower()
            ->title()
            ->toString();
    }


    public static function handoverStatus(mixed $value): string
    {
        return match ((string) $value) {
            'pending' => 'Menunggu Tanggapan Mitra',
            'accepted' => 'Permintaan Diterima',
            'offered' => 'Menunggu Respons Penawaran',
            'offer_accepted' => 'Kesepakatan Diterima',
            'completed' => 'Penyerahan Selesai',
            'declined' => 'Ditolak Mitra',
            'offer_rejected' => 'Penawaran Ditolak Warga',
            'cancelled_by_user' => 'Dibatalkan Warga',
            'cancelled_by_partner' => 'Dibatalkan Mitra',
            'expired' => 'Kedaluwarsa',
            'closed' => 'Ditutup',
            default => self::label($value),
        };
    }

    public static function isVerifiedOutcome(mixed $path): bool
    {
        return in_array((string) $path, [
            'REUSED',
            'REPAIRED',
            'DONATED',
            'PARTS_RECOVERED',
            'RECOVERY_CONFIRMED',
            'SPECIAL_HANDLING_COMPLETED',
        ], true);
    }

    public static function isTransferDecision(mixed $path): bool
    {
        return app(\App\Services\AssetFlowService::class)->isTransferDecision((string) $path);
    }

    public static function isClosedDisposition(mixed $path): bool
    {
        return in_array((string) $path, [
            'RETURNED_TO_OWNER',
            'UNVERIFIED_FINAL_TREATMENT',
            'SPLIT_TO_SUB_BATCHES',
        ], true);
    }

    public static function assetProgress(mixed $status, mixed $finalPath = null): string
    {
        if ($finalPath) {
            return self::label($finalPath);
        }

        return match ((string) $status) {
            'registered' => 'Menunggu Cek Kondisi',
            'assessed', 'matching' => 'Menunggu Pemilihan Mitra',
            'requested' => 'Menunggu Tanggapan Mitra',
            'partner_accepted', 'offered', 'scheduled' => 'Menunggu Penyerahan Barang',
            'received' => 'Sedang Diperiksa Mitra',
            'in_processing' => 'Sedang Ditangani Mitra',
            'awaiting_donation_proof' => 'Menunggu Penyaluran & Bukti Donasi',
            'needs_transfer' => 'Perlu Mitra Lanjutan',
            'transfer_pending' => 'Menunggu Konfirmasi Mitra Tujuan',
            'transferred' => 'Sedang Dialihkan',
            default => self::label($status),
        };
    }

    public static function whatsappUrl(?string $number, ?string $message = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if (!$digits)
            return null;
        if (str_starts_with($digits, '0'))
            $digits = '62' . substr($digits, 1);
        if (!str_starts_with($digits, '62'))
            $digits = '62' . $digits;
        if (strlen($digits) < 8 || strlen($digits) > 15)
            return null;

        $url = 'https://wa.me/' . $digits;
        if (filled($message))
            $url .= '?text=' . rawurlencode((string) $message);
        return $url;
    }

    public static function resource(?string $class): string
    {
        if (!$class) {
            return '-';
        }

        $base = class_basename($class);

        return self::RESOURCES[$base] ?? self::label($base);
    }
}
