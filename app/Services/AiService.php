<?php

namespace App\Services;

use App\Models\{AiResult,AiUsageLog,Asset,IntakeSession,SystemSetting};
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AiService
{
    private ?string $lastFailureCode = null;
    private ?string $lastFailureMessage = null;

    public function lastFailureMessage(): ?string
    {
        return $this->lastFailureMessage;
    }

    public function userFacingFailureMessage(string $fallback = 'Bantuan AI sedang tidak tersedia. Coba lagi nanti atau lanjutkan secara manual.'): string
    {
        return match ($this->lastFailureCode) {
            'no_photos' => 'Pilih minimal 1 foto terlebih dahulu.',
            'unreadable_photos' => 'Foto belum dapat dibaca. Coba pilih ulang foto JPG, PNG, atau WebP.',
            'empty_catalog', 'category_unresolved' => 'Saran belum dapat dibuat untuk foto ini. Anda tetap dapat memilih jenis barang secara manual.',
            'budget_reached' => 'Bantuan AI sedang tidak tersedia untuk sementara. Anda tetap dapat melanjutkan secara manual.',
            'provider_rate_limit' => 'Bantuan AI sedang ramai digunakan. Tunggu sebentar lalu coba lagi.',
            'disabled', 'not_configured', 'provider_auth', 'provider_server', 'provider_request', 'provider_connection', 'provider_unavailable', 'empty_response', 'invalid_json' => 'Bantuan AI sedang tidak tersedia. Anda tetap dapat melanjutkan secara manual.',
            default => $fallback,
        };
    }

    private function rememberFailure(string $code, string $message): void
    {
        $this->lastFailureCode = $code;
        $this->lastFailureMessage = $message;
    }

    private function clearFailure(): void
    {
        $this->lastFailureCode = null;
        $this->lastFailureMessage = null;
    }

    /**
     * Analisis foto sebelum barang disimpan. Hasilnya hanya draft untuk membantu
     * mengisi form; method ini tidak membuat Asset, tidak menyimpan AiResult, dan
     * tidak pernah mengubah data warga tanpa konfirmasi di browser.
     */
    public function draftIntake(array $photos, iterable $categories): ?array
    {
        $this->clearFailure();

        if (empty($photos)) {
            $this->rememberFailure('no_photos', 'Pilih minimal 1 foto sebelum menjalankan bantuan AI.');
            return null;
        }

        if (! filled(config('sirkel.ai.api_key'))) {
            $this->rememberFailure('not_configured', 'Koneksi AI belum dikonfigurasi pada server. Periksa OPENAI_API_KEY.');
            return null;
        }

        if (! (bool) SystemSetting::getValue('ai.enabled', true)) {
            $this->rememberFailure('disabled', 'AI sedang dinonaktifkan dari pengaturan admin.');
            return null;
        }

        $catalog = [];
        $byCode = [];
        foreach ($categories as $category) {
            $code = (string) $category->code;
            $catalog[] = [
                'code' => $code,
                'name' => (string) $category->name,
                'group' => (string) ($category->group?->name ?? ''),
                'supports_batch' => (bool) $category->supports_batch,
                'requires_custom_name' => $category->requiresCustomName(),
            ];
            $byCode[$code] = $category;
        }

        if ($catalog === []) {
            $this->rememberFailure('empty_catalog', 'Katalog kategori belum tersedia untuk bantuan AI.');
            return null;
        }

        $prompt = 'Anda adalah asisten pengisian form SIRKEL untuk BARANG ELEKTRONIK FISIK dan e-waste. Ini adalah mode pendaftaran biasa, bukan Bulk AI: 1-3 foto boleh dipakai hanya sebagai beberapa sudut dari SATU JENIS BARANG atau satu kelompok barang sejenis. Sebelum memberi saran form, lakukan dua pemeriksaan. Pertama, eligibility_status wajib salah satu: supported, unsupported, uncertain. Gunakan supported bila subjek utama jelas berupa perangkat listrik/elektronik fisik, peralatan rumah tangga listrik, baterai, kabel/charger, aksesori elektronik, komponen elektronik, atau barang fisik lain yang wajar masuk alur e-waste. Mainan elektronik seperti mobil RC/remote, robot mainan, drone mini, atau mainan bertenaga baterai/listrik termasuk supported; baterai atau motor tidak harus terlihat dari luar bila identitas barang secara visual cukup kuat menunjukkan mainan elektronik. Mainan mekanis, die-cast, miniatur statis, atau mainan biasa tanpa indikasi elektronik tetap unsupported/uncertain. Gunakan unsupported bila gambar jelas bukan barang elektronik fisik, misalnya tangkapan layar aplikasi/website, dokumen, teks, orang, makanan, furnitur, kendaraan non-elektrik, atau objek biasa tanpa hubungan elektronik. Tangkapan layar Zoom/WhatsApp/browser adalah unsupported. Foto FISIK laptop/monitor/ponsel tetap supported walaupun layarnya sedang menampilkan aplikasi. Kedua, scope_status wajib salah satu: single_type, multiple_types, uncertain. Gunakan single_type hanya bila seluruh foto dan seluruh subjek utama yang hendak didaftarkan menunjukkan satu jenis barang/sekelompok barang sejenis. Gunakan multiple_types bila terlihat dua atau lebih jenis elektronik berbeda, termasuk bila beberapa jenis berbeda berada dalam satu foto, misalnya kulkas + mesin cuci + microwave, atau foto pertama kulkas dan foto kedua televisi. Jika multiple_types, jangan pilih satu category_code secara paksa; detected_types isi daftar nama singkat jenis yang terlihat dan scope_reason jelaskan bahwa pendaftaran biasa hanya untuk satu jenis barang serta sarankan Bulk AI. Jika eligibility_status bukan supported, category_code dan custom_item_name harus null, description harus null, dan eligibility_reason jelaskan singkat kenapa foto belum sesuai; jangan memaksa ke kategori Elektronik Lainnya. Jika scope_status bukan single_type, category_code dan custom_item_name harus null, description harus null. Jika supported dan single_type, analisis HANYA hal yang tampak dan jangan membuat diagnosis teknis, keputusan penanganan, atau klaim fungsi yang tidak terlihat. Pilih tepat satu category_code dari katalog. Jika perangkat elektronik fisik memang tidak memiliki kategori spesifik, pilih kategori Lainnya pada kelompok paling dekat; kategori belum tahu hanya untuk barang elektronik fisik yang kelompoknya benar-benar tidak dapat dipastikan. Hitung hanya jumlah barang sejenis yang BENAR-BENAR TERLIHAT; jangan menebak di luar frame. Jika jumlah tidak dapat dipastikan, visible_quantity boleh null. same_item_group hanya true bila barang yang terlihat memang sejenis dan layak dicatat bersama. description adalah KONDISI VISUAL SINGKAT untuk mengisi form, bukan caption gambar: maksimal 1-2 kalimat Bahasa Indonesia, natural, fokus pada kondisi fisik relevan seperti utuh, retak, pecah, penyok, aus, kabel terkelupas, komponen hilang, karat, bekas terbakar, bengkak, atau kerusakan yang benar-benar tampak. Jangan mengulang warna, bentuk, jumlah, atau jenis barang bila tidak diperlukan karena informasi itu sudah ada pada field lain. Jika tidak terlihat kerusakan mencolok, gunakan gaya seperti "Secara visual tampak cukup utuh dan belum terlihat kerusakan mencolok." Jangan menulis diagnosis internal atau fungsi yang tidak dapat diketahui dari foto. custom_item_name hanya diisi bila category_code membutuhkan nama tambahan. Kembalikan HANYA JSON valid dengan schema: {"eligibility_status":"supported","eligibility_reason":"","scope_status":"single_type","scope_reason":"","detected_types":[""],"detected_name":"","category_code":"","custom_item_name":null,"visible_quantity":1,"same_item_group":true,"description":"","confidence":0.0}. Katalog kategori: '.json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $content = [['type' => 'input_text', 'text' => $prompt]];
        $imageHashes = [];

        foreach (array_slice(array_values($photos), 0, 3) as $photo) {
            try {
                $path = $photo?->getRealPath();
                if (! $path || ! is_file($path)) {
                    continue;
                }
                $bytes = file_get_contents($path);
                if ($bytes === false || $bytes === '') {
                    continue;
                }
                $mime = $photo->getMimeType() ?: 'image/jpeg';
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
                    'detail' => SystemSetting::getValue('ai.image_detail', config('sirkel.ai.image_detail')),
                ];
                $imageHashes[] = hash('sha256', $bytes);
            } catch (Throwable) {
                // Satu foto yang tidak terbaca tidak boleh menggagalkan foto lain.
            }
        }

        if ($imageHashes === []) {
            $this->rememberFailure('unreadable_photos', 'Foto tidak dapat dibaca untuk analisis AI. Coba pilih ulang foto JPG, PNG, atau WebP.');
            return null;
        }

        $hash = hash('sha256', json_encode([$imageHashes, $catalog], JSON_UNESCAPED_UNICODE));
        $result = $this->callJson('asset_intake', null, $content, $hash);
        if (! $result) {
            if (! $this->lastFailureMessage) {
                $this->rememberFailure('provider_unavailable', 'Analisis AI belum berhasil. Coba lagi beberapa saat atau isi form secara manual.');
            }
            return null;
        }

        $data = $result['data'];
        $eligibilityStatus = strtolower(trim((string) ($data['eligibility_status'] ?? 'uncertain')));
        if (! in_array($eligibilityStatus, ['supported', 'unsupported', 'uncertain'], true)) {
            $eligibilityStatus = 'uncertain';
        }

        $detectedNameRaw = trim(strip_tags((string) ($data['detected_name'] ?? '')));
        $eligibilityReason = trim(strip_tags((string) ($data['eligibility_reason'] ?? '')));
        $confidence = isset($data['confidence']) && is_numeric($data['confidence'])
            ? max(0, min(1, (float) $data['confidence']))
            : null;

        $scopeStatus = strtolower(trim((string) ($data['scope_status'] ?? 'single_type')));
        if (! in_array($scopeStatus, ['single_type', 'multiple_types', 'uncertain'], true)) {
            $scopeStatus = 'uncertain';
        }
        $scopeReason = trim(strip_tags((string) ($data['scope_reason'] ?? '')));
        $detectedTypes = collect(is_array($data['detected_types'] ?? null) ? $data['detected_types'] : [])
            ->map(fn ($value) => mb_substr(trim(strip_tags((string) $value)), 0, 80))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->take(5)
            ->values()
            ->all();

        // Pendaftaran biasa hanya mewakili satu jenis barang. Bila beberapa jenis
        // elektronik berbeda terlihat, jangan paksa semuanya menjadi satu kategori.
        if ($scopeStatus !== 'single_type') {
            $detectedSummary = $detectedTypes !== []
                ? implode(', ', $detectedTypes)
                : ($detectedNameRaw ?: 'beberapa jenis barang');

            return [
                'eligible' => false,
                'eligibility_status' => $eligibilityStatus,
                'scope_status' => $scopeStatus,
                'requires_bulk' => $scopeStatus === 'multiple_types',
                'eligibility_reason' => mb_substr(
                    $scopeReason ?: (
                        $scopeStatus === 'multiple_types'
                            ? 'Terlihat beberapa jenis barang berbeda. Pendaftaran biasa hanya untuk satu jenis barang; gunakan Bulk AI untuk memproses beberapa jenis sekaligus.'
                            : 'Belum dapat dipastikan bahwa semua foto menunjukkan satu jenis barang yang sama. Pilih foto yang hanya menampilkan satu jenis barang.'
                    ),
                    0,
                    280
                ),
                'detected_name' => mb_substr($detectedSummary, 0, 160),
                'detected_types' => $detectedTypes,
                'confidence' => $confidence,
            ];
        }

        // Mainan kendaraan/robot yang dikenali sebagai kandidat elektronik boleh
        // diteruskan sebagai saran dengan konfirmasi warga. Ini menghindari false
        // negative ketika baterai/motor tertutup casing, tanpa menganggap semua
        // mainan sebagai e-waste.
        $toyCandidate = $eligibilityStatus === 'uncertain'
            && $this->looksLikeElectronicToyCandidate($detectedNameRaw, $eligibilityReason)
            && isset($byCode['other-gaming-electronics']);
        if ($toyCandidate) {
            $eligibilityStatus = 'supported';
            $data['category_code'] = 'other-gaming-electronics';
            $data['custom_item_name'] = $data['custom_item_name'] ?? ($detectedNameRaw ?: 'Mainan elektronik');
            $eligibilityReason = 'Terlihat seperti mainan yang dapat menggunakan baterai/listrik/remote. Gunakan saran ini hanya jika barang memang elektronik; jika mainan mekanis atau miniatur biasa, isi secara manual.';
        }

        // Jangan pernah memaksa tangkapan layar atau objek non-elektronik ke
        // "Elektronik Lainnya". Untuk hasil meragukan, bantuan AI berhenti sebagai
        // gate saja; warga tetap dapat mengganti foto atau mengisi form manual.
        if ($eligibilityStatus !== 'supported' || ($confidence !== null && $confidence < 0.45 && ! $toyCandidate)) {
            if ($eligibilityStatus === 'supported') {
                $eligibilityStatus = 'uncertain';
                $eligibilityReason = $eligibilityReason ?: 'AI belum cukup yakin bahwa objek pada foto dapat dikenali sebagai barang elektronik yang sesuai.';
            }

            return [
                'eligible' => false,
                'eligibility_status' => $eligibilityStatus,
                'eligibility_reason' => mb_substr(
                    $eligibilityReason ?: (
                        $eligibilityStatus === 'unsupported'
                            ? 'Foto tidak menunjukkan barang elektronik fisik yang sesuai untuk pendaftaran SIRKEL.'
                            : 'Belum dapat dipastikan dari foto apakah objek tersebut merupakan barang elektronik yang sesuai.'
                    ),
                    0,
                    280
                ),
                'detected_name' => mb_substr($detectedNameRaw ?: 'Objek belum dapat dipastikan', 0, 120),
                'confidence' => $confidence,
            ];
        }

        $code = trim((string) ($data['category_code'] ?? ''));
        $category = $byCode[$code] ?? null;
        if (! $category) {
            $fallbackCode = (string) config('sirkel_catalog.uncategorized_code', 'uncategorized-electronics');
            $category = $byCode[$fallbackCode] ?? null;
        }
        if (! $category) {
            $this->rememberFailure('category_unresolved', 'AI berhasil membaca foto elektronik, tetapi kategori hasilnya belum dapat dipetakan ke katalog SIRKEL.');
            return null;
        }

        $visibleQuantity = filter_var($data['visible_quantity'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 999],
        ]);
        $visibleQuantity = $visibleQuantity === false ? null : $visibleQuantity;
        $sameItemGroup = filter_var($data['same_item_group'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $trackingType = $visibleQuantity !== null
            && $visibleQuantity >= 2
            && $sameItemGroup
            && (bool) $category->supports_batch
                ? 'batch'
                : 'individual';

        $detectedName = $detectedNameRaw !== '' ? $detectedNameRaw : (string) $category->name;
        $customName = null;
        if ($category->requiresCustomName()) {
            $customName = trim(strip_tags((string) ($data['custom_item_name'] ?? $detectedName)));
            $customName = $customName !== '' ? mb_substr($customName, 0, 120) : null;
        }

        $description = trim(strip_tags((string) ($data['description'] ?? '')));
        $description = $description !== '' ? mb_substr($description, 0, 1200) : null;
        return [
            'eligible' => true,
            'eligibility_status' => 'supported',
            'scope_status' => 'single_type',
            'requires_bulk' => false,
            'needs_electronic_confirmation' => $toyCandidate,
            'eligibility_reason' => mb_substr($eligibilityReason, 0, 280),
            'detected_name' => mb_substr($detectedName ?: (string) $category->name, 0, 120),
            'category_id' => (int) $category->id,
            'category_code' => (string) $category->code,
            'category_name' => (string) $category->name,
            'group_name' => (string) ($category->group?->name ?? ''),
            'requires_custom_name' => $category->requiresCustomName(),
            'custom_item_name' => $customName,
            'supports_batch' => (bool) $category->supports_batch,
            'tracking_type' => $trackingType,
            'visible_quantity' => $visibleQuantity,
            'description' => $description,
            'confidence' => $confidence,
        ];
    }

    private function looksLikeElectronicToyCandidate(string $detectedName, string $reason): bool
    {
        $text = mb_strtolower(trim($detectedName.' '.$reason));
        if ($text === '') {
            return false;
        }

        foreach (['diecast', 'die-cast', 'miniatur statis', 'model statis', 'mekanis', 'non-elektronik', 'tanpa elektronik'] as $blocked) {
            if (str_contains($text, $blocked)) {
                return false;
            }
        }

        foreach ([
            'mobil mainan', 'mainan mobil', 'mobil rc', 'rc car', 'remote control car',
            'motor mainan', 'mainan motor', 'truk mainan', 'mainan truk',
            'robot mainan', 'mainan robot', 'drone mainan', 'mainan remote', 'mainan rc',
        ] as $candidate) {
            if (str_contains($text, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bulk AI: satu analisis foto dapat menghasilkan maksimal lima kelompok barang.
     * Barang sejenis digabung server-side agar satu jenis tidak menghabiskan slot.
     */
    public function draftBulkIntake(array $photos, iterable $categories): ?array
    {
        $this->clearFailure();

        if (empty($photos)) {
            $this->rememberFailure('no_photos', 'Pilih minimal 1 foto untuk memulai Bulk AI.');
            return null;
        }
        if (! filled(config('sirkel.ai.api_key'))) {
            $this->rememberFailure('not_configured', 'Koneksi AI belum dikonfigurasi pada server.');
            return null;
        }
        if (! (bool) SystemSetting::getValue('ai.enabled', true)) {
            $this->rememberFailure('disabled', 'AI sedang dinonaktifkan dari pengaturan admin.');
            return null;
        }

        $catalog = [];
        $byCode = [];
        foreach ($categories as $category) {
            $code = (string) $category->code;
            $catalog[] = [
                'code' => $code,
                'name' => (string) $category->name,
                'group' => (string) ($category->group?->name ?? ''),
                'requires_custom_name' => $category->requiresCustomName(),
                'supports_batch' => (bool) $category->supports_batch,
            ];
            $byCode[$code] = $category;
        }
        if ($catalog === []) {
            $this->rememberFailure('empty_catalog', 'Katalog kategori belum tersedia.');
            return null;
        }

        $prompt = 'Anda adalah Bulk AI SIRKEL untuk membantu warga mendaftarkan BANYAK BARANG ELEKTRONIK FISIK dalam satu sesi. '
            .'Analisis seluruh foto sebagai satu konteks. Kembalikan hanya barang elektronik/e-waste fisik yang benar-benar terlihat. '
            .'GABUNGKAN objek dengan kategori/jenis yang sama menjadi satu kelompok Bulk walaupun berupa perangkat besar. Contoh dua kulkas tetap Kulkas quantity 2 dan tiga kabel charger tetap Kabel Charger quantity 3; jelaskan perbedaan unit pada unit_observations. '
            .'Jangan memecah barang sejenis hanya karena bentuk/model/kondisi visual unit berbeda. Pisahkan hanya jika category_code memang berbeda atau untuk kategori Lainnya nama barangnya jelas berbeda. Jika kondisi unit berbeda, simpan rinciannya pada unit_observations. '
            .'Maksimal keluarkan 5 kelompok. Jangan mengarang barang di luar frame. category_code harus tepat satu dari katalog. '
            .'Jika suatu barang elektronik tidak punya kategori spesifik, gunakan kategori Lainnya yang paling dekat atau uncategorized-electronics. '
            .'description adalah ringkasan visual natural maksimal 2 kalimat dan tidak boleh mendiagnosis fungsi internal. unit_observations boleh berisi rincian singkat bernomor untuk kondisi unit yang berbeda. '
            .'eligibility_status wajib supported, unsupported, atau uncertain. unsupported untuk screenshot, dokumen, orang, makanan, furnitur, atau objek non-elektronik. '
            .'Kembalikan HANYA JSON valid: {"eligibility_status":"supported","eligibility_reason":"","items":[{"detected_name":"","category_code":"","custom_item_name":null,"quantity":1,"description":"","unit_observations":[],"confidence":0.0}]}. '
            .'Katalog: '.json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $content = [['type' => 'input_text', 'text' => $prompt]];
        $imageHashes = [];
        foreach (array_slice(array_values($photos), 0, 3) as $photo) {
            try {
                $path = $photo?->getRealPath();
                if (! $path || ! is_file($path)) continue;
                $bytes = file_get_contents($path);
                if ($bytes === false || $bytes === '') continue;
                $mime = $photo->getMimeType() ?: 'image/jpeg';
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
                    'detail' => SystemSetting::getValue('ai.image_detail', config('sirkel.ai.image_detail')),
                ];
                $imageHashes[] = hash('sha256', $bytes);
            } catch (Throwable) {}
        }
        if ($imageHashes === []) {
            $this->rememberFailure('unreadable_photos', 'Foto belum dapat dibaca untuk Bulk AI.');
            return null;
        }

        $hash = hash('sha256', json_encode(['bulk-v1', $imageHashes, $catalog], JSON_UNESCAPED_UNICODE));
        $result = $this->callJson('bulk_ai_detect', null, $content, $hash);
        if (! $result) return null;

        $data = $result['data'];
        $eligibility = strtolower(trim((string) ($data['eligibility_status'] ?? 'uncertain')));
        if ($eligibility !== 'supported') {
            return [
                'eligible' => false,
                'eligibility_status' => in_array($eligibility, ['unsupported','uncertain'], true) ? $eligibility : 'uncertain',
                'eligibility_reason' => mb_substr(trim(strip_tags((string) ($data['eligibility_reason'] ?? 'Foto belum cukup jelas untuk Bulk AI.'))), 0, 300),
                'items' => [],
            ];
        }

        $merged = [];
        foreach ((array) ($data['items'] ?? []) as $raw) {
            if (! is_array($raw)) continue;
            $code = trim((string) ($raw['category_code'] ?? ''));
            $category = $byCode[$code] ?? ($byCode[(string) config('sirkel_catalog.uncategorized_code', 'uncategorized-electronics')] ?? null);
            if (! $category) continue;

            $quantity = filter_var($raw['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
            $quantity = $quantity === false ? 1 : (int) $quantity;
            $custom = $category->requiresCustomName()
                ? mb_substr(trim(strip_tags((string) ($raw['custom_item_name'] ?? $raw['detected_name'] ?? $category->name))), 0, 120)
                : null;
            $observations = array_values(array_filter(array_map(
                static fn ($v) => mb_substr(trim(strip_tags((string) $v)), 0, 240),
                (array) ($raw['unit_observations'] ?? [])
            )));
            $description = mb_substr(trim(strip_tags((string) ($raw['description'] ?? ''))), 0, 1200);
            $supportsBatch = (bool) $category->supports_batch;

            // Mode Bulk memiliki grouping sendiri: objek dengan kategori yang sama
            // menjadi satu ItemGroup agar limit 5 menghitung jenis/kelompok, bukan unit fisik.
            // supports_batch tetap dipertahankan sebagai metadata katalog untuk mode Standard.
            $instances = 1;
            for ($instance = 0; $instance < $instances; $instance++) {
                $key = (string) $category->id.'|'.mb_strtolower((string) $custom);

                if (! isset($merged[$key])) {
                    $unitDescription = $description;
                    $merged[$key] = [
                        'category_id' => (int) $category->id,
                        'category_code' => (string) $category->code,
                        'category_name' => (string) $category->name,
                        'group_name' => (string) ($category->group?->name ?? ''),
                        'requires_custom_name' => $category->requiresCustomName(),
                        'supports_batch' => $supportsBatch,
                        'custom_item_name' => $custom ?: null,
                        'detected_name' => mb_substr(trim(strip_tags((string) ($raw['detected_name'] ?? $category->name))), 0, 120),
                        'quantity' => 0,
                        'tracking_type' => 'individual',
                        'description' => $unitDescription,
                        'description_parts' => $unitDescription !== '' ? [$unitDescription] : [],
                        'unit_observations' => [],
                        'confidence' => isset($raw['confidence']) && is_numeric($raw['confidence']) ? max(0, min(1, (float) $raw['confidence'])) : null,
                    ];
                }

                $merged[$key]['quantity'] += $quantity;
                $merged[$key]['tracking_type'] = $merged[$key]['quantity'] > 1 ? 'batch' : 'individual';
                $merged[$key]['unit_observations'] = array_values(array_slice(array_merge($merged[$key]['unit_observations'], $observations), 0, 20));
                if ($description !== '' && ! in_array($description, $merged[$key]['description_parts'], true)) {
                    $merged[$key]['description_parts'][] = $description;
                }
            }
        }

        $items = array_values(array_slice($merged, 0, 5, true));
        if ($items === []) {
            $this->rememberFailure('category_unresolved', 'AI belum menemukan kelompok elektronik yang dapat dipetakan ke katalog SIRKEL.');
            return null;
        }

        foreach ($items as &$item) {
            $details = [];
            foreach ($item['unit_observations'] as $i => $observation) $details[] = ($i + 1).'. '.$observation;
            if ($details) {
                $base = $item['description_parts'][0] ?? $item['description'];
                $item['description'] = trim(($base ? $base."\n" : '').implode("\n", $details));
            } elseif (count($item['description_parts']) > 1) {
                $item['description'] = implode("\n", array_map(
                    static fn ($part, $i) => ($i + 1).'. '.$part,
                    $item['description_parts'],
                    array_keys($item['description_parts'])
                ));
            } else {
                $item['description'] = $item['description_parts'][0] ?? $item['description'];
            }
            $item['description'] = mb_substr($item['description'] ?: 'Kondisi visual akan dikonfirmasi oleh warga pada sesi Bulk AI.', 0, 1200);
            unset($item['unit_observations'], $item['description_parts']);
        }
        unset($item);

        return [
            'eligible' => true,
            'eligibility_status' => 'supported',
            'eligibility_reason' => mb_substr(trim(strip_tags((string) ($data['eligibility_reason'] ?? ''))), 0, 300),
            'items' => $items,
        ];
    }

    /**
     * AI memilih pertanyaan minimum untuk seluruh sesi. 15 adalah hard ceiling,
     * bukan target. Pertanyaan boleh berupa matrix untuk beberapa kelompok sekaligus.
     */
    public function bulkAdaptiveQuestionnaire(IntakeSession $session): ?array
    {
        $this->clearFailure();
        $session->loadMissing('items.asset.category.group');
        $assets = $session->items->pluck('asset')->filter()->values();
        if ($assets->isEmpty()) {
            $this->rememberFailure('empty_bulk_session', 'Belum ada barang dalam sesi Bulk AI.');
            return null;
        }

        $allowedSignals = [
            'power_status', 'damage_level', 'technician_result', 'user_intent', 'hazard_sign',
            'battery_swollen', 'battery_leaking', 'cooling_leak', 'burn_damage',
        ];
        $allowedSignalValues = [
            'power_status' => ['normal', 'partial', 'off', 'unknown'],
            'damage_level' => ['none', 'minor', 'moderate', 'severe', 'unknown'],
            'technician_result' => ['not_checked', 'repairable', 'not_repairable', 'unknown'],
            'user_intent' => ['reuse', 'donate', 'safe_handover', 'recycle', 'unsure'],
            'hazard_sign' => ['yes', 'no', 'unknown'],
            'battery_swollen' => ['yes', 'no', 'unknown'],
            'battery_leaking' => ['yes', 'no', 'unknown'],
            'cooling_leak' => ['yes', 'no', 'unknown'],
            'burn_damage' => ['yes', 'no', 'unknown'],
        ];
        $items = $assets->map(fn (Asset $asset) => [
            'id' => $asset->public_id,
            'name' => $asset->custom_item_name ?: $asset->category?->name,
            'category_code' => $asset->category?->code,
            'category_name' => $asset->category?->name,
            'group' => $asset->category?->group?->name,
            'quantity' => $asset->quantity,
            'visual_description' => $asset->description,
        ])->all();

        $prompt = 'Anda menyusun Adaptive Bulk Questionnaire SIRKEL. Tujuannya memperoleh informasi MINIMUM yang belum diketahui untuk membantu Rule Engine menilai beberapa kelompok elektronik sekaligus. '
            .'JANGAN mengejar jumlah tertentu. Gunakan sesedikit mungkin pertanyaan; hard maximum 15 untuk SELURUH sesi. Gabungkan pertanyaan yang dapat dijawab untuk beberapa item sebagai matrix. '
            .'Jangan menanyakan ulang fakta visual yang sudah jelas dari deskripsi foto. Prioritaskan fungsi, kerusakan fisik yang memengaruhi jalur circular, dan keselamatan. '
            .'Untuk barang yang mengandung baterai (battery, powerbank, ups, smartphone, feature-phone, tablet, laptop, smartwatch, e-reader, camera, handheld-console), pastikan informasi relevan tentang menggembung/kebocoran/panas atau bekas terbakar tidak terlewat; boleh digabung menjadi satu matrix_multi. '
            .'signal_key hanya boleh salah satu: '.implode(', ', $allowedSignals).'. '
            .'Tipe yang diizinkan: matrix_single, matrix_multi, single, multi, text. matrix berarti pengguna menjawab untuk masing-masing target item dalam SATU pertanyaan. '
            .'Untuk matrix_single/single gunakan signal_key dan opsi value yang kompatibel dengan Rule Engine: power_status={normal,partial,off,unknown}; damage_level={none,minor,moderate,severe,unknown}; technician_result={not_checked,repairable,not_repairable,unknown}; user_intent={reuse,donate,safe_handover,recycle,unsure}; hazard_sign={yes,no,unknown}; battery_swollen/battery_leaking/cooling_leak/burn_damage={yes,no,unknown}. '
            .'Untuk matrix_multi/multi yang merangkum bahaya, gunakan signal_map seperti {"swollen":"battery_swollen","leaking":"battery_leaking","burn":"burn_damage","none":"none","unknown":"none"}. '
            .'targets harus berisi id item dari input. Pertanyaan text boleh signal_key null dan hanya untuk klarifikasi yang benar-benar perlu. '
            .'Kembalikan HANYA JSON valid: {"questions":[{"text":"","type":"matrix_single","targets":["..."],"required":true,"signal_key":"power_status","signal_map":{},"options":[{"value":"normal","label":"Berfungsi normal"}]}]}. '
            .'Data kelompok: '.json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $hash = hash('sha256', json_encode(['bulk-questionnaire-v1', $items], JSON_UNESCAPED_UNICODE));
        $result = $this->callJson('bulk_ai_questionnaire', null, [['type' => 'input_text', 'text' => $prompt]], $hash);
        if (! $result) return null;

        $validIds = array_fill_keys(array_map(fn ($a) => (string) $a['id'], $items), true);
        $questions = [];
        foreach (array_slice((array) ($result['data']['questions'] ?? []), 0, 15) as $raw) {
            if (! is_array($raw)) continue;
            $text = mb_substr(trim(strip_tags((string) ($raw['text'] ?? ''))), 0, 500);
            $type = (string) ($raw['type'] ?? 'single');
            if ($text === '' || ! in_array($type, ['matrix_single','matrix_multi','single','multi','text'], true)) continue;
            $targets = array_values(array_unique(array_filter(array_map('strval', (array) ($raw['targets'] ?? [])), fn ($id) => isset($validIds[$id]))));
            if ($targets === []) continue;

            $signalKey = $raw['signal_key'] ?? null;
            $signalKey = in_array($signalKey, $allowedSignals, true) ? $signalKey : null;
            $options = [];
            foreach (array_slice((array) ($raw['options'] ?? []), 0, 8) as $option) {
                if (! is_array($option)) continue;
                $value = mb_substr(trim((string) ($option['value'] ?? '')), 0, 80);
                $label = mb_substr(trim(strip_tags((string) ($option['label'] ?? $value))), 0, 160);
                if ($value !== '' && $label !== '') $options[] = ['value' => $value, 'label' => $label];
            }
            if (in_array($type, ['single', 'matrix_single'], true)) {
                if (! $signalKey) continue;
                $allowedValues = $allowedSignalValues[$signalKey] ?? [];
                $options = array_values(array_filter($options, fn ($option) => in_array((string) $option['value'], $allowedValues, true)));
            }
            if ($type !== 'text' && $options === []) continue;

            $signalMap = [];
            foreach ((array) ($raw['signal_map'] ?? []) as $value => $signal) {
                if ($signal === 'none' || in_array($signal, $allowedSignals, true)) {
                    $signalMap[mb_substr((string) $value, 0, 80)] = $signal;
                }
            }
            if (in_array($type, ['multi', 'matrix_multi'], true) && $signalMap === []) continue;

            $questions[] = [
                'id' => 'q'.(count($questions) + 1),
                'text' => $text,
                'type' => $type,
                'targets' => $targets,
                'required' => (bool) ($raw['required'] ?? true),
                'signal_key' => $signalKey,
                'signal_map' => $signalMap,
                'options' => $options,
            ];
        }

        if ($questions === []) {
            $this->rememberFailure('invalid_ai_json', 'AI belum menghasilkan pertanyaan Bulk yang dapat digunakan.');
            return null;
        }

        $questions = $this->ensureBulkSafetyQuestions($questions, $items);
        $questions = array_values(array_slice($questions, 0, 15));
        foreach ($questions as $index => &$question) $question['id'] = 'q'.($index + 1);
        unset($question);

        return $questions;
    }

    private function ensureBulkSafetyQuestions(array $questions, array $items): array
    {
        $allTargets = array_values(array_map(fn ($item) => (string) $item['id'], $items));
        $batteryCodes = ['battery', 'powerbank', 'ups', 'smartphone', 'feature-phone', 'tablet', 'laptop', 'smartwatch', 'e-reader', 'camera', 'handheld-console'];
        $coolingCodes = ['refrigerator', 'freezer', 'air-conditioner'];
        $batteryTargets = array_values(array_map(
            fn ($item) => (string) $item['id'],
            array_filter($items, fn ($item) => in_array((string) ($item['category_code'] ?? ''), $batteryCodes, true))
        ));
        $coolingTargets = array_values(array_map(
            fn ($item) => (string) $item['id'],
            array_filter($items, fn ($item) => in_array((string) ($item['category_code'] ?? ''), $coolingCodes, true))
        ));

        $questionSignals = static function (array $question): array {
            return array_values(array_unique(array_filter(array_merge(
                [(string) ($question['signal_key'] ?? '')],
                array_map('strval', array_values((array) ($question['signal_map'] ?? [])))
            ), fn ($signal) => $signal !== '' && $signal !== 'none')));
        };

        $coversSignal = function (string $signal, array $targets) use ($questions, $questionSignals): bool {
            if ($targets === []) return true;
            $covered = [];
            foreach ($questions as $question) {
                if (! (bool) ($question['required'] ?? true)) continue;
                if (! in_array($signal, $questionSignals($question), true)) continue;
                foreach ((array) ($question['targets'] ?? []) as $target) $covered[(string) $target] = true;
            }
            foreach ($targets as $target) if (! isset($covered[$target])) return false;
            return true;
        };

        $guardrails = [];

        // Rule Engine tetap membutuhkan beberapa signal inti. AI bebas menyusun atau
        // menggabungkan pertanyaan sendiri; fallback ini hanya ditambahkan bila signal
        // tersebut belum tercakup untuk seluruh kelompok. Dengan matrix, satu pertanyaan
        // dapat menjawab banyak barang sekaligus tanpa kembali ke questionnaire per-item.
        if (! $coversSignal('power_status', $allTargets)) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Untuk masing-masing barang, bagaimana kondisi fungsinya berdasarkan yang benar-benar Anda ketahui?',
                'type' => 'matrix_single',
                'targets' => $allTargets,
                'required' => true,
                'signal_key' => 'power_status',
                'signal_map' => [],
                'options' => [
                    ['value' => 'normal', 'label' => 'Berfungsi normal'],
                    ['value' => 'partial', 'label' => 'Masih berfungsi tetapi bermasalah'],
                    ['value' => 'off', 'label' => 'Tidak berfungsi / tidak menyala'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu / belum dapat diuji'],
                ],
            ];
        }

        if (! $coversSignal('damage_level', $allTargets)) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Untuk masing-masing barang, bagaimana kondisi fisiknya secara umum?',
                'type' => 'matrix_single',
                'targets' => $allTargets,
                'required' => true,
                'signal_key' => 'damage_level',
                'signal_map' => [],
                'options' => [
                    ['value' => 'none', 'label' => 'Tidak ada kerusakan yang diketahui'],
                    ['value' => 'minor', 'label' => 'Kerusakan ringan'],
                    ['value' => 'moderate', 'label' => 'Kerusakan sedang'],
                    ['value' => 'severe', 'label' => 'Kerusakan berat'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu / belum yakin'],
                ],
            ];
        }

        if (! $coversSignal('user_intent', $allTargets)) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Untuk masing-masing barang, apa yang paling Anda inginkan setelah kondisi barang diperiksa?',
                'type' => 'matrix_single',
                'targets' => $allTargets,
                'required' => true,
                'signal_key' => 'user_intent',
                'signal_map' => [],
                'options' => [
                    ['value' => 'reuse', 'label' => 'Dimanfaatkan kembali jika masih memungkinkan'],
                    ['value' => 'donate', 'label' => 'Disalurkan / didonasikan jika masih layak'],
                    ['value' => 'safe_handover', 'label' => 'Diserahkan dengan aman'],
                    ['value' => 'recycle', 'label' => 'Pemulihan material jika sudah tidak layak'],
                    ['value' => 'unsure', 'label' => 'Bantu rekomendasikan'],
                ],
            ];
        }

        if (! $coversSignal('hazard_sign', $allTargets)) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Untuk masing-masing barang, apakah ada tanda bahaya seperti panas tidak wajar, bau menyengat, meleleh, atau bekas terbakar?',
                'type' => 'matrix_single',
                'targets' => $allTargets,
                'required' => true,
                'signal_key' => 'hazard_sign',
                'signal_map' => [],
                'options' => [
                    ['value' => 'yes', 'label' => 'Ya, ada tanda bahaya'],
                    ['value' => 'no', 'label' => 'Tidak ada yang diketahui'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu / belum yakin'],
                ],
            ];
        }

        $batteryCovered = $coversSignal('battery_swollen', $batteryTargets) && $coversSignal('battery_leaking', $batteryTargets);
        if ($batteryTargets !== [] && ! $batteryCovered) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Untuk barang yang memiliki baterai, kondisi mana yang Anda ketahui pada masing-masing barang?',
                'type' => 'matrix_multi',
                'targets' => $batteryTargets,
                'required' => true,
                'signal_key' => null,
                'signal_map' => [
                    'swollen' => 'battery_swollen',
                    'leaking' => 'battery_leaking',
                    'burn' => 'burn_damage',
                    'none' => 'none',
                    'unknown' => 'none',
                ],
                'options' => [
                    ['value' => 'swollen', 'label' => 'Menggembung / bentuk berubah'],
                    ['value' => 'leaking', 'label' => 'Bocor / ada cairan atau korosi tidak biasa'],
                    ['value' => 'burn', 'label' => 'Ada bekas terbakar / meleleh'],
                    ['value' => 'none', 'label' => 'Tidak ada kondisi tersebut'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu / belum yakin'],
                ],
            ];
        }

        if ($coolingTargets !== [] && ! $coversSignal('cooling_leak', $coolingTargets)) {
            $guardrails[] = [
                'id' => '',
                'text' => 'Apakah terlihat atau pernah diketahui ada kebocoran pada sistem pendingin barang berikut?',
                'type' => 'matrix_single',
                'targets' => $coolingTargets,
                'required' => true,
                'signal_key' => 'cooling_leak',
                'signal_map' => [],
                'options' => [
                    ['value' => 'yes', 'label' => 'Ya / ada indikasi kebocoran'],
                    ['value' => 'no', 'label' => 'Tidak ada yang diketahui'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu / belum yakin'],
                ],
            ];
        }

        if ($guardrails === []) return array_values(array_slice($questions, 0, 15));

        // Sisakan ruang untuk guardrail tanpa pernah melewati 15. Jika harus memangkas
        // keluaran AI, pertanyaan AI yang sudah membawa signal keselamatan diprioritaskan.
        $slots = max(0, 15 - count($guardrails));
        $safetySignals = ['power_status', 'damage_level', 'user_intent', 'hazard_sign', 'battery_swollen', 'battery_leaking', 'cooling_leak', 'burn_damage'];
        $priorityIndexes = [];
        $normalIndexes = [];
        foreach ($questions as $index => $question) {
            $isSafety = array_intersect($questionSignals($question), $safetySignals) !== [];
            if ($isSafety) {
                $priorityIndexes[] = $index;
            } else {
                $normalIndexes[] = $index;
            }
        }
        $keepIndexes = array_slice(array_merge($priorityIndexes, $normalIndexes), 0, $slots);
        sort($keepIndexes);
        $kept = [];
        foreach ($keepIndexes as $index) $kept[] = $questions[$index];

        return array_values(array_merge($kept, $guardrails));
    }

    public function intake(Asset $asset, ?string $freeText = null, bool $force = false): array
    {
        $primary = $asset->photos()->where('is_primary', true)->first() ?? $asset->photos()->first();
        $fingerprint = hash('sha256', json_encode([$asset->device_category_id,$asset->custom_item_name,$asset->brand,$asset->model_name,$asset->description,$freeText,$primary?->path]));
        if (!$force && ($cached = AiResult::where(['feature'=>'asset_intake','asset_id'=>$asset->id,'input_hash'=>$fingerprint])->first())) return $cached->result_json;
        if (!$this->available()) return $this->fallbackIntake($asset, $freeText, 'AI tidak aktif; klasifikasi manual tetap tersedia.');

        $prompt = 'Anda membantu platform circular e-waste SIRKEL di Surabaya. Identifikasi perangkat dari data/foto, normalisasi keluhan tanpa mendiagnosis, dan flag keselamatan awal. Kembalikan HANYA JSON valid: {"detected_name":"", "category_hint":"", "normalized_description":"", "confidence":0.0, "safety_flags":[], "needs_more_info":false}. Bahasa Indonesia. Jangan membuat klaim keselamatan atau diagnosis final.';
        $content = [['type'=>'input_text','text'=>$prompt."\nInput pengguna: ".($freeText ?: $asset->description ?: $asset->custom_item_name ?: 'Tidak ada')]];
        if ($primary && Storage::disk('public')->exists($primary->path)) {
            try {
                $bytes = Storage::disk('public')->get($primary->path);
                $mime = Storage::disk('public')->mimeType($primary->path) ?: 'image/jpeg';
                $content[] = ['type'=>'input_image','image_url'=>'data:'.$mime.';base64,'.base64_encode($bytes),'detail'=>SystemSetting::getValue('ai.image_detail', config('sirkel.ai.image_detail'))];
            } catch (Throwable) {}
        }

        $result = $this->callJson('asset_intake', $asset, $content, $fingerprint);
        if (!$result) return $this->fallbackIntake($asset, $freeText, 'Analisis AI tidak tersedia saat ini.');
        $data = $result['data'];

        $threshold = (float) SystemSetting::getValue('ai.escalation_confidence', config('sirkel.ai.escalation_confidence'));
        $confidence = isset($data['confidence']) ? (float) $data['confidence'] : null;
        $escalation = (string) SystemSetting::getValue('ai.escalation_model', config('sirkel.ai.escalation_model'));
        if ($confidence !== null && $confidence < $threshold && $escalation && $escalation !== $result['_model'] && !$this->budgetReached()) {
            $extra = [['type'=>'input_text','text'=>'Analisis ulang karena confidence tahap pertama rendah. Tetap keluarkan JSON dengan schema yang sama. Prioritaskan identifikasi perangkat dan flag keselamatan; jangan diagnosis final. Hasil tahap pertama: '.json_encode($data, JSON_UNESCAPED_UNICODE)]];
            if (isset($content[1])) $extra[] = $content[1];
            $second = $this->callJson('asset_intake_escalation', $asset, $extra, $fingerprint.'-escalated', $escalation);
            if ($second && (float)($second['data']['confidence'] ?? 0) >= $confidence) {
                $data = $second['data'];
                $result = $second;
            }
        }

        AiResult::updateOrCreate(
            ['feature'=>'asset_intake','asset_id'=>$asset->id,'input_hash'=>$fingerprint],
            ['model'=>$result['_model'],'result_json'=>$data,'confidence'=>$data['confidence'] ?? null]
        );
        return $data;
    }

    public function explain(Asset $asset, array $answers, array $rule): array
    {
        $fingerprint = hash('sha256', json_encode([$asset->id,$answers,$rule]));
        if ($cached = AiResult::where(['feature'=>'assessment_explanation','asset_id'=>$asset->id,'input_hash'=>$fingerprint])->first()) return $cached->result_json;
        if (!$this->available()) return ['citizen_explanation'=>$rule['explanation'],'partner_summary'=>$rule['explanation']];

        $content = [['type'=>'input_text','text'=>'Kembalikan HANYA JSON valid {"citizen_explanation":"", "partner_summary":""}. Jelaskan hasil rule SIRKEL dengan bahasa awam dan ringkas. Jangan diagnosis, jangan menjamin keamanan, dan jelaskan bahwa mitra melakukan pemeriksaan final. Data: '.json_encode(['asset'=>$asset->only(['custom_item_name','brand','model_name','description']),'answers'=>$answers,'rule'=>$rule], JSON_UNESCAPED_UNICODE)]];
        $result = $this->callJson('assessment_explanation', $asset, $content, $fingerprint);
        $data = $result['data'] ?? ['citizen_explanation'=>$rule['explanation'],'partner_summary'=>$rule['explanation']];
        if ($result) AiResult::updateOrCreate(['feature'=>'assessment_explanation','asset_id'=>$asset->id,'input_hash'=>$fingerprint], ['model'=>$result['_model'],'result_json'=>$data]);
        return $data;
    }

    public function partnerNarrative(Asset $asset, array $assessment, string $finalPath): ?string
    {
        if (!$this->available()) return null;
        $hash = hash('sha256', json_encode([$asset->id,$assessment,$finalPath]));
        if ($cached = AiResult::where(['feature'=>'partner_assessment_narrative','asset_id'=>$asset->id,'input_hash'=>$hash])->first()) return $cached->result_json['summary'] ?? null;
        $result = $this->callJson('partner_assessment_narrative', $asset, [['type'=>'input_text','text'=>'Kembalikan HANYA JSON valid {"summary":""}. Buat ringkasan assessment teknis mitra SIRKEL dalam Bahasa Indonesia maksimal 3 kalimat. Jangan menambah fakta yang tidak ada dan jangan membuat diagnosis di luar input. Data: '.json_encode(['assessment'=>$assessment,'final_path'=>$finalPath], JSON_UNESCAPED_UNICODE)]], $hash);
        $summary = $result['data']['summary'] ?? null;
        if ($result && $summary) AiResult::updateOrCreate(['feature'=>'partner_assessment_narrative','asset_id'=>$asset->id,'input_hash'=>$hash], ['model'=>$result['_model'],'result_json'=>['summary'=>$summary]]);
        return $summary;
    }

    public function citizenConditionDescription(Asset $asset, array $answeredQuestions): ?string
    {
        $this->clearFailure();

        if (! filled(config('sirkel.ai.api_key'))) {
            $this->rememberFailure('not_configured', 'Koneksi AI belum dikonfigurasi pada server. Periksa OPENAI_API_KEY.');
            return null;
        }
        if (! (bool) SystemSetting::getValue('ai.enabled', true)) {
            $this->rememberFailure('disabled', 'AI sedang dinonaktifkan dari pengaturan admin.');
            return null;
        }

        $asset->loadMissing('category');
        $hash = hash('sha256', json_encode([
            $asset->id,
            $asset->category?->code,
            $asset->custom_item_name,
            $answeredQuestions,
        ], JSON_UNESCAPED_UNICODE));

        if ($cached = AiResult::where([
            'feature' => 'citizen_condition_description',
            'asset_id' => $asset->id,
            'input_hash' => $hash,
        ])->first()) {
            return $cached->result_json['description'] ?? null;
        }

        $conditionQuestions = collect($answeredQuestions)
            ->reject(fn (array $item) => in_array((string) ($item['kode'] ?? ''), ['user_intent', 'notes'], true))
            ->values()
            ->all();

        $prompt = 'Anda membantu warga menulis CATATAN TAMBAHAN kondisi barang untuk mitra SIRKEL. Jangan mengubah semua jawaban menjadi paragraf. Pilih hanya informasi yang benar-benar berguna sebagai gejala, kondisi tidak normal, tanda bahaya, ketidakpastian penting, atau riwayat pemeriksaan yang relevan. Abaikan tujuan penyerahan/keinginan warga karena itu bukan kondisi barang. Jangan mengulang jawaban normal seperti “berfungsi normal”, “tidak ada kerusakan berarti”, “tidak terlihat tanda bahaya”, atau “belum pernah diperiksa” kecuali dibutuhkan untuk memahami gejala lain. Jika tidak ada gejala atau kondisi tambahan yang berarti, jawab tepat: “Tidak ada gejala tambahan yang perlu dicatat.” Gunakan Bahasa Indonesia natural, maksimal 1-2 kalimat. Jangan menambah fakta, jangan mendiagnosis penyebab teknis, jangan menentukan jalur repair/recovery, dan jangan memberi keputusan penanganan. Jika ada jawaban “tidak tahu”, sebutkan hanya bila ketidakpastian itu penting. Kembalikan teks biasa tanpa judul, bullet, markdown, atau tanda kutip. Data barang: '.json_encode([
            'barang' => $asset->custom_item_name ?: $asset->category?->name,
            'kategori' => $asset->category?->name,
            'jawaban_kondisi' => $conditionQuestions,
        ], JSON_UNESCAPED_UNICODE);

        $text = $this->callText(
            'citizen_condition_description',
            $asset,
            [['type' => 'input_text', 'text' => $prompt]],
            $hash
        );

        $description = trim((string) $text);
        if ($description === '') return null;
        $description = mb_substr($description, 0, 1200);

        AiResult::updateOrCreate(
            ['feature' => 'citizen_condition_description', 'asset_id' => $asset->id, 'input_hash' => $hash],
            ['model' => (string) SystemSetting::getValue('ai.default_model', config('sirkel.ai.default_model')), 'result_json' => ['description' => $description]]
        );

        return $description;
    }

    public function adminImpactNarrative(array $impact): string
    {
        if (!$this->available()) return 'AI sedang tidak aktif. Gunakan angka dashboard terverifikasi sebagai sumber laporan.';
        return $this->callText('admin_impact_narrative', null, [['type'=>'input_text','text'=>'Buat ringkasan dampak SIRKEL dalam Bahasa Indonesia maksimal 120 kata berdasarkan data terverifikasi berikut. Hindari overclaim dan bedakan recovery confirmed dari outcome yang belum terverifikasi: '.json_encode($impact, JSON_UNESCAPED_UNICODE)]], hash('sha256', json_encode($impact))) ?: 'Narasi AI belum tersedia.';
    }

    private function callJson(string $feature, ?Asset $asset, array $content, string $hash, ?string $model = null): ?array
    {
        $response = $this->request($feature, $asset, $content, $hash, $model);
        if (! $response) return null;
        $decoded = json_decode($this->cleanJson($response['text']), true);
        if (! is_array($decoded)) {
            // Provider memang mengonsumsi token, tetapi user tidak menerima hasil yang
            // dapat dipakai. Simpan biayanya di ledger namun jangan potong Kuota AI.
            AiUsageLog::query()
                ->where('feature', $feature)
                ->where('user_id', auth()->id())
                ->where('request_hash', $hash)
                ->where('status', 'success')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'failed',
                    'error_message' => 'Provider mengembalikan output yang tidak dapat dibaca sebagai JSON terstruktur.',
                ]);

            $this->rememberFailure('invalid_ai_json', 'AI merespons, tetapi hasil analisis belum dapat dibaca. Coba proses lagi.');
            return null;
        }
        return ['_model'=>$response['_model'],'data'=>$decoded];
    }

    private function callText(string $feature, ?Asset $asset, array $content, string $hash, ?string $model = null): ?string
    {
        return $this->request($feature, $asset, $content, $hash, $model)['text'] ?? null;
    }

    private function request(string $feature, ?Asset $asset, array $content, string $hash, ?string $model = null): ?array
    {
        if ($this->budgetReached()) {
            $this->rememberFailure('budget_reached', 'Batas penggunaan AI bulan ini sudah tercapai. Foto tetap dapat digunakan tanpa AI.');
            return null;
        }

        $model ??= (string) SystemSetting::getValue('ai.default_model', config('sirkel.ai.default_model'));
        $started = microtime(true);
        $maxAttempts = max(1, (int) config('sirkel.ai.max_attempts', 2));
        $connectTimeout = max(1, (int) config('sirkel.ai.connect_timeout', 20));
        $requestTimeout = max($connectTimeout, (int) config('sirkel.ai.request_timeout', 60));
        $retryDelays = array_values(array_map(
            static fn ($delay) => max(0, (int) $delay),
            (array) config('sirkel.ai.retry_delays_ms', [750])
        ));

        // Http::timeout() controls Guzzle/cURL, but it cannot outlive PHP's own
        // max_execution_time. On Windows/local development the wall-clock time
        // spent inside cURL also counts, so a 60s provider timeout with PHP's
        // default 30s limit used to end as an uncatchable FatalError. Give only
        // this AI request enough execution room. If the host forbids changing the
        // limit, narrow the HTTP budget and disable retries so Laravel can return
        // the normal graceful AI fallback before PHP kills the request.
        [$connectTimeout, $requestTimeout, $maxAttempts] = $this->prepareExecutionBudget(
            $connectTimeout,
            $requestTimeout,
            $maxAttempts,
            $retryDelays
        );

        $lastError = null;
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                $delayMs = $retryDelays[min($attempt - 2, max(0, count($retryDelays) - 1))] ?? 750;
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            try {
                $res = Http::withToken(config('sirkel.ai.api_key'))
                    ->acceptJson()
                    ->connectTimeout($connectTimeout)
                    ->timeout($requestTimeout)
                    ->post(rtrim(config('sirkel.ai.base_url'), '/').'/responses', [
                        'model'=>$model,
                        'input'=>[['role'=>'user','content'=>$content]],
                    ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $text = is_array($json) ? $this->extractResponseText($json) : '';
                    if ($text !== '') {
                        $this->log(
                            $feature,
                            $asset,
                            $model,
                            $json['usage'] ?? [],
                            (int) ((microtime(true)-$started)*1000),
                            'success',
                            $hash
                        );

                        return ['_model'=>$model,'text'=>$text];
                    }

                    $lastError = 'HTTP 200 tetapi output teks tidak ditemukan pada response.';
                    $this->rememberFailure('empty_ai_output', 'AI merespons tanpa hasil teks yang dapat dibaca. Coba proses lagi.');
                    break;
                }

                $status = $res->status();
                $lastStatus = $status;
                $body = trim((string) $res->body());
                $lastError = 'HTTP '.$status.($body !== '' ? ': '.$body : '');

                // Retry hanya untuk kondisi yang memang mungkin pulih tanpa mengubah input.
                $retryable = $status === 408 || $status === 429 || $status >= 500;
                if (!$retryable || $attempt >= $maxAttempts) {
                    break;
                }
            } catch (ConnectionException $e) {
                // DNS/connect/TLS timeout bersifat transient. SIRKEL mencoba ulang sebelum fallback.
                $lastError = $e->getMessage();
                if ($attempt >= $maxAttempts) {
                    break;
                }
            } catch (Throwable $e) {
                // Error non-network tidak diulang agar tidak menimbulkan call/cost yang tidak perlu.
                $lastError = $e->getMessage();
                break;
            }
        }

        if (! $this->lastFailureMessage) {
            if (in_array($lastStatus, [401, 403], true)) {
                $this->rememberFailure('provider_auth', 'Koneksi AI ditolak oleh penyedia. Periksa API key pada server.');
            } elseif ($lastStatus === 429) {
                $this->rememberFailure('provider_rate_limit', 'Layanan AI sedang membatasi permintaan. Tunggu sebentar lalu coba lagi.');
            } elseif ($lastStatus !== null && $lastStatus >= 500) {
                $this->rememberFailure('provider_server', 'Layanan AI sedang mengalami gangguan. Coba lagi beberapa saat.');
            } elseif ($lastStatus !== null) {
                $this->rememberFailure('provider_request', 'Permintaan AI ditolak oleh layanan. Admin dapat memeriksa log penggunaan AI untuk detail teknis.');
            } else {
                $this->rememberFailure('provider_connection', 'Koneksi ke layanan AI belum berhasil. Periksa koneksi server lalu coba lagi.');
            }
        }

        $this->log(
            $feature,
            $asset,
            $model,
            [],
            (int) ((microtime(true)-$started)*1000),
            'failed',
            $hash,
            $lastError ?: 'Request AI gagal tanpa detail error.'
        );

        return null;
    }

    /**
     * Keep the provider HTTP budget inside PHP's execution window.
     *
     * @return array{0:int,1:int,2:int} connect timeout, request timeout, attempts
     */
    private function prepareExecutionBudget(int $connectTimeout, int $requestTimeout, int $maxAttempts, array $retryDelays): array
    {
        $currentLimit = (int) ini_get('max_execution_time');

        // 0 means unlimited. There is nothing to extend and no reason to impose
        // a new limit on a server that intentionally runs without one.
        if ($currentLimit <= 0) {
            return [$connectTimeout, $requestTimeout, $maxAttempts];
        }

        $retryBudgetMs = 0;
        for ($i = 0; $i < max(0, $maxAttempts - 1); $i++) {
            $retryBudgetMs += (int) ($retryDelays[min($i, max(0, count($retryDelays) - 1))] ?? 750);
        }

        $requiredSeconds = (int) ceil(
            ($requestTimeout * $maxAttempts)
            + ($retryBudgetMs / 1000)
            + 10
        );
        $configuredWindow = max(1, (int) config('sirkel.ai.execution_timeout', 150));
        $targetWindow = max($requiredSeconds, $configuredWindow);

        if ($currentLimit < $requiredSeconds) {
            // Hosts differ: some honor ini_set(), some only set_time_limit(), and
            // some forbid both. Suppress configuration warnings and verify below.
            @ini_set('max_execution_time', (string) $targetWindow);
            if (function_exists('set_time_limit')) {
                @set_time_limit($targetWindow);
            }
        }

        $effectiveLimit = (int) ini_get('max_execution_time');
        if ($effectiveLimit <= 0 || $effectiveLimit >= $requiredSeconds) {
            return [$connectTimeout, $requestTimeout, $maxAttempts];
        }

        // Shared hosting may lock max_execution_time. In that case, fail through
        // Laravel's ConnectionException path before the hard PHP deadline instead
        // of rendering a FatalError page. One attempt also prevents the retry from
        // crossing the same execution ceiling (and avoids duplicate AI cost).
        $buffer = max(3, (int) config('sirkel.ai.execution_fallback_buffer', 8));
        $safeRequestTimeout = max(1, $effectiveLimit - $buffer);

        return [
            max(1, min($connectTimeout, $safeRequestTimeout)),
            max(1, min($requestTimeout, $safeRequestTimeout)),
            1,
        ];
    }

    private function extractResponseText(array $json): string
    {
        $direct = trim((string) ($json['output_text'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        foreach ((array) ($json['output'] ?? []) as $item) {
            if (! is_array($item)) continue;
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (! is_array($part)) continue;
                $type = (string) ($part['type'] ?? '');
                $text = trim((string) ($part['text'] ?? ''));
                if ($text !== '' && in_array($type, ['output_text', 'text'], true)) {
                    return $text;
                }
            }
        }

        return '';
    }

    private function cleanJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        return trim($text);
    }

    private function log(string $feature, ?Asset $asset, string $model, array $u, int $lat, string $status, string $hash, ?string $error = null): void
    {
        $input = (int)($u['input_tokens'] ?? 0);
        $cached = (int)($u['input_tokens_details']['cached_tokens'] ?? 0);
        $output = (int)($u['output_tokens'] ?? 0);
        AiUsageLog::create([
            'feature'=>$feature,'user_id'=>auth()->id(),'asset_id'=>$asset?->id,'partner_profile_id'=>auth()->user()?->partnerProfile?->id,
            'model'=>$model,'input_tokens'=>$input,'cached_input_tokens'=>$cached,'output_tokens'=>$output,
            'estimated_cost_usd'=>$this->estimate($model,$input,$cached,$output),'latency_ms'=>$lat,'status'=>$status,'request_hash'=>$hash,
            'error_message'=>$error ? mb_substr($error,0,2000) : null,
        ]);
    }

    private function estimate(string $model, int $input, int $cached, int $output): float
    {
        // Rates are intentionally isolated here and should be reviewed from the current provider pricing page before production launch.
        $prices = ['gpt-5.6-luna'=>[.20,.02,1.20],'gpt-5.6-terra'=>[2,.20,12],'gpt-5.6-sol'=>[4,.40,20]];
        $p = $prices[$model] ?? $prices['gpt-5.6-luna'];
        $normal = max(0,$input-$cached);
        return (($normal*$p[0])+($cached*$p[1])+($output*$p[2]))/1_000_000;
    }

    private function available(): bool
    {
        return filled(config('sirkel.ai.api_key')) && (bool) SystemSetting::getValue('ai.enabled', true);
    }

    private function budgetReached(): bool
    {
        $budget = (float) SystemSetting::getValue('ai.monthly_budget_usd', config('sirkel.ai.monthly_budget_usd'));
        $used = (float) AiUsageLog::whereBetween('created_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('estimated_cost_usd');
        return $budget > 0 && $used >= $budget;
    }

    private function fallbackIntake(Asset $asset, ?string $text, string $message): array
    {
        return ['detected_name'=>$asset->custom_item_name ?: $asset->category?->name,'category_hint'=>$asset->category?->code,'normalized_description'=>$text ?: $asset->description,'confidence'=>null,'safety_flags'=>[],'needs_more_info'=>true,'fallback_message'=>$message];
    }
}
