<?php

namespace App\Services;

use App\Models\{Asset, AssetAssessment, PartnerProfile};

class AssetFlowService
{
    public const TRANSFER_DECISIONS = [
        'TRANSFER_REPAIR' => 'repair',
        'TRANSFER_REUSE_DONATION' => 'reuse_donation',
        'TRANSFER_RECOVERY' => 'recovery',
        'TRANSFER_SPECIAL_HANDLING' => 'special_handling',
    ];

    /**
     * Layanan pertama yang dibutuhkan setelah cek kondisi warga.
     * Jenis penyerahan (jual/gratis/donasi) tidak boleh mengalahkan safety/technical routing.
     */
    public function initialCapability(Asset $asset): string
    {
        return app(RuleEngine::class)->capabilityFor((string) $asset->preliminary_path);
    }

    public function isTransferDecision(?string $decision): bool
    {
        if (!$decision) {
            return false;
        }

        return $decision === 'TRANSFER_REQUIRED' || array_key_exists($decision, self::TRANSFER_DECISIONS);
    }

    public function transferCapabilityForDecision(string $decision): ?string
    {
        return self::TRANSFER_DECISIONS[$decision] ?? null;
    }

    /** @return array<int,string> */
    public function approvedCapabilities(PartnerProfile $profile): array
    {
        if ($profile->relationLoaded('capabilities')) {
            return $profile->capabilities
                ->where('status', 'approved')
                ->pluck('capability')
                ->values()
                ->all();
        }

        return $profile->capabilities()
            ->where('status', 'approved')
            ->pluck('capability')
            ->all();
    }

    /**
     * Hasil akhir yang boleh diklaim oleh mitra saat ini.
     * Donation adalah tujuan warga: "REPAIRED" bukan hasil akhir jika barang masih harus disalurkan untuk donasi.
     *
     * @return array<int,array{code:string,label:string,help:string,tone?:string}>
     */
    public function completionOptions(Asset $asset, PartnerProfile $profile): array
    {
        $caps = $this->approvedCapabilities($profile);
        $options = [];
        $donationGoal = $asset->handover_type === 'donation';

        if (in_array('repair', $caps, true) && !$donationGoal) {
            $options[] = [
                'code' => 'REPAIRED',
                'label' => 'Selesai — dipulihkan sebagai perangkat utuh',
                'help' => 'Pilih jika proses perbaikan/refurbish sudah selesai dan perangkat aman serta layak masuk kembali ke jalur penggunaan. Barang tidak dikembalikan sebagai layanan servis kepada warga.',
            ];
        }

        if (in_array('reuse_donation', $caps, true)) {
            if (!$donationGoal) {
                $options[] = [
                    'code' => 'REUSED',
                    'label' => 'Selesai — disalurkan untuk digunakan kembali',
                    'help' => 'Pilih jika barang sudah benar-benar masuk jalur penggunaan kembali dan tidak membutuhkan penanganan teknis lanjutan.',
                ];
            }

            $options[] = [
                'code' => 'DONATED',
                'label' => 'Siap disalurkan — lanjutkan ke Bukti Donasi',
                'help' => 'Pilih setelah pemeriksaan memastikan barang aman dan layak disalurkan. Outcome belum selesai sampai foto, waktu, dan lokasi penyaluran dicatat pada Bukti Donasi.',
            ];
        }

        if (in_array('recovery', $caps, true)) {
            $options[] = [
                'code' => 'PARTS_RECOVERED',
                'label' => 'Selesai — komponen dipulihkan di mitra ini',
                'help' => $donationGoal
                    ? 'Gunakan jika tujuan donasi tidak dapat dipenuhi dan pemulihan komponen benar-benar sudah dilakukan.'
                    : 'Pilih jika komponen yang masih bernilai guna benar-benar sudah dipulihkan.',
            ];
            $options[] = [
                'code' => 'RECOVERY_CONFIRMED',
                'label' => 'Selesai — material dipulihkan di mitra ini',
                'help' => $donationGoal
                    ? 'Gunakan hanya jika barang tidak layak didonasikan dan proses pemulihan material benar-benar sudah terkonfirmasi.'
                    : 'Pilih hanya jika proses pemulihan material benar-benar sudah dapat dipastikan selesai.',
            ];
        }

        if (in_array('special_handling', $caps, true)) {
            $options[] = [
                'code' => 'SPECIAL_HANDLING_COMPLETED',
                'label' => 'Selesai — penanganan khusus terkonfirmasi',
                'help' => 'Pilih hanya jika kondisi berisiko sudah ditangani sesuai prosedur mitra dan penanganan khusus pada barang ini memang selesai.',
            ];
        }

        return $options;
    }

    /**
     * Menyimpan hasil pemeriksaan tanpa memalsukan outcome akhir.
     * Dipakai ketika barang masih perlu dikerjakan oleh mitra yang sama
     * (mis. proses repair/recovery/penyaluran belum selesai hari itu).
     *
     * @return array{code:string,label:string,help:string}
     */
    public function continuationOption(PartnerProfile $profile): array
    {
        $caps = $this->approvedCapabilities($profile);
        $primary = collect(['repair', 'recovery', 'reuse_donation', 'special_handling'])
            ->first(fn(string $cap) => in_array($cap, $caps, true));

        return [
            'code' => 'CONTINUE_HANDLING',
            'label' => match ($primary) {
                'repair' => 'Belum selesai — lanjut proses perbaikan di mitra ini',
                'recovery' => 'Belum selesai — lanjut proses pemulihan di mitra ini',
                'reuse_donation' => 'Belum selesai — lanjut proses penyaluran di mitra ini',
                'special_handling' => 'Belum selesai — lanjut penanganan khusus di mitra ini',
                default => 'Belum selesai — lanjut ditangani oleh mitra ini',
            },
            'help' => 'Pilih ini untuk menyimpan hasil pemeriksaan sekarang tanpa menutup barang. Barang tetap berada di menu Barang Ditangani sampai proses selesai atau perlu dialihkan.',
        ];
    }

    /**
     * Pilihan pengalihan yang valid. Target dipilih eksplisit agar barang tidak dapat dialihkan ke mitra yang salah layanan.
     *
     * @return array<int,array{code:string,capability:string,label:string,help:string,recommended:bool}>
     */
    public function transferOptions(Asset $asset, PartnerProfile $profile): array
    {
        $caps = $this->approvedCapabilities($profile);
        $recommended = $this->recommendedNextCapabilities($asset, $caps);
        $repairRuledOut = $this->repairRuledOut($asset);
        $generalCandidates = [];
        if (in_array('special_handling', $caps, true) && count(array_intersect($caps, ['repair', 'reuse_donation', 'recovery'])) === 0) {
            $generalCandidates = ['recovery'];
        } else {
            if (
                !$repairRuledOut
                && !in_array('repair', $caps, true)
                && (in_array('reuse_donation', $caps, true) || in_array('recovery', $caps, true) || $this->initialCapability($asset) === 'repair')
            ) {
                $generalCandidates[] = 'repair';
            }
            if (!in_array('reuse_donation', $caps, true) && ($asset->handover_type === 'donation' || in_array('repair', $caps, true) || in_array('recovery', $caps, true) || $this->initialCapability($asset) === 'reuse_donation')) {
                $generalCandidates[] = 'reuse_donation';
            }
            if (!in_array('recovery', $caps, true)) {
                $generalCandidates[] = 'recovery';
            }
            // Selalu sediakan jalur Penanganan Khusus sebagai kandidat untuk mitra yang
            // tidak memilikinya. UI/guard akan mengaktifkannya hanya ketika pemeriksaan
            // menemukan risiko atau kondisi awal memang memerlukannya.
            if (!in_array('special_handling', $caps, true)) {
                $generalCandidates[] = 'special_handling';
            }
        }

        $candidateCaps = collect($recommended)
            ->merge($generalCandidates)
            ->unique()
            ->reject(fn(string $cap) => in_array($cap, $caps, true))
            ->values();

        return $candidateCaps->map(function (string $capability) use ($recommended) {
            $code = array_search($capability, self::TRANSFER_DECISIONS, true);
            return [
                'code' => $code,
                'capability' => $capability,
                'label' => match ($capability) {
                    'repair' => 'Lanjutkan ke Mitra Perbaikan',
                    'reuse_donation' => 'Lanjutkan ke Mitra Guna Ulang & Donasi',
                    'recovery' => 'Lanjutkan ke Mitra Pemulihan Material',
                    'special_handling' => 'Lanjutkan ke Mitra Penanganan Khusus',
                    default => 'Lanjutkan ke Mitra Lain',
                },
                'help' => match ($capability) {
                    'repair' => 'Pilih jika barang masih perlu pemeriksaan/perbaikan teknis sebelum dapat ditentukan hasil akhirnya.',
                    'reuse_donation' => 'Pilih jika barang perlu diteruskan untuk digunakan kembali atau memenuhi tujuan donasi warga.',
                    'recovery' => 'Pilih jika barang tidak layak digunakan/diperbaiki dan perlu pemulihan komponen atau material.',
                    'special_handling' => 'Pilih jika terdapat kondisi berisiko seperti baterai menggembung, bocor, atau kerusakan yang memerlukan penanganan khusus.',
                    default => 'Pilih jika layanan lain diperlukan untuk menyelesaikan penanganan.',
                },
                'recommended' => in_array($capability, $recommended, true),
            ];
        })->all();
    }

    /** @return array<int,string> */
    private function recommendedNextCapabilities(Asset $asset, array $currentCaps): array
    {
        $recommended = [];
        $initial = $this->initialCapability($asset);
        $repairRuledOut = $this->repairRuledOut($asset);

        if ($asset->handover_type === 'donation' && !in_array('reuse_donation', $currentCaps, true)) {
            // Donasi adalah tujuan akhir jika kondisi memungkinkan, tetapi safety tetap lebih tinggi.
            if ($asset->preliminary_path === 'SPECIAL_HANDLING') {
                $recommended[] = 'special_handling';
            } elseif (
                in_array($asset->preliminary_path, ['REPAIR_ASSESSMENT', 'TECHNICAL_ASSESSMENT'], true)
                && !in_array('repair', $currentCaps, true)
            ) {
                $recommended[] = 'repair';
            } else {
                $recommended[] = 'reuse_donation';
            }
        }

        if (
            !in_array($initial, $currentCaps, true)
            && !in_array($initial, ['collection', 'pickup'], true)
            && !($initial === 'repair' && $repairRuledOut)
        ) {
            $recommended[] = $initial;
        }

        if (in_array('repair', $currentCaps, true)) {
            if ($asset->handover_type === 'donation') {
                $recommended[] = 'reuse_donation';
            }
            $recommended[] = 'recovery';
        }

        if (in_array('reuse_donation', $currentCaps, true)) {
            if (!$repairRuledOut) {
                $recommended[] = 'repair';
            }
            $recommended[] = 'recovery';
        }

        if (in_array('special_handling', $currentCaps, true)) {
            $recommended[] = 'recovery';
        }

        if (in_array('recovery', $currentCaps, true)) {
            // Circular hierarchy: jika ternyata masih layak, barang dapat diarahkan kembali ke jalur bernilai lebih tinggi.
            if ($asset->handover_type === 'donation') {
                $recommended[] = 'reuse_donation';
            } elseif (!$repairRuledOut) {
                $recommended[] = 'repair';
            }
        }

        return array_values(array_unique(array_filter($recommended)));
    }

    /** @return array<int,string> */
    public function allowedDecisionCodes(Asset $asset, PartnerProfile $profile): array
    {
        return array_values(array_unique(array_merge(
            array_column($this->completionOptions($asset, $profile), 'code'),
            array_column($this->transferOptions($asset, $profile), 'code'),
            ['CONTINUE_HANDLING', 'UNVERIFIED_FINAL_TREATMENT']
        )));
    }

    public function isDecisionAllowed(Asset $asset, PartnerProfile $profile, string $decision): bool
    {
        // TRANSFER_REQUIRED hanya dibaca untuk histori lama. Request baru wajib menentukan layanan tujuan secara eksplisit.
        if ($decision === 'TRANSFER_REQUIRED') {
            return false;
        }

        return in_array($decision, $this->allowedDecisionCodes($asset, $profile), true);
    }

    public function assessmentConflictMessage(Asset $asset, PartnerProfile $profile, string $decision, array $answers): ?string
    {
        $power = $answers['power_status'] ?? 'unknown';
        $damage = $answers['damage_level'] ?? 'unknown';
        $repairFeasible = $answers['repair_feasible'] ?? 'unknown';
        $hazard = ($answers['hazard_found'] ?? 'unknown') === 'yes'
            || ($answers['refrigerant_risk'] ?? 'unknown') === 'yes'
            || ($answers['power_safety'] ?? null) === 'hazard'
            || ($answers['hygiene_condition'] ?? null) === 'unsafe';
        $recoveryPotential = $answers['recovery_potential'] ?? 'unknown';
        $ordinaryFinals = ['REPAIRED', 'REUSED', 'DONATED', 'PARTS_RECOVERED', 'RECOVERY_CONFIRMED'];

        if ($hazard && in_array($decision, $ordinaryFinals, true)) {
            return 'Pemeriksaan mencatat kondisi yang memerlukan penanganan khusus. Selesaikan penanganan khusus di mitra ini jika memiliki layanan tersebut, atau alihkan ke Mitra Penanganan Khusus sebelum menutup hasil akhir biasa.';
        }

        if ($hazard && in_array($decision, ['TRANSFER_REPAIR', 'TRANSFER_REUSE_DONATION', 'TRANSFER_RECOVERY'], true)) {
            return 'Kondisi berisiko harus ditangani terlebih dahulu. Pilih Penanganan Khusus, bukan layanan biasa, selama risiko tersebut belum diselesaikan.';
        }

        if ($decision === 'SPECIAL_HANDLING_COMPLETED' && !$hazard && $asset->preliminary_path !== 'SPECIAL_HANDLING') {
            return 'Belum ada kondisi khusus yang tercatat pada pemeriksaan ini. Gunakan hasil Penanganan Khusus hanya bila memang ada risiko yang sudah ditangani sesuai prosedur.';
        }

        if ($decision === 'REPAIRED') {
            if ($repairFeasible !== 'yes') {
                return 'Hasil “Dipulihkan sebagai Perangkat Utuh” hanya boleh dipilih jika pemeriksaan menyatakan perangkat masih layak dipertahankan melalui perbaikan/refurbish dan prosesnya sudah selesai.';
            }
            if (!in_array($power, ['normal', 'partial'], true) || $damage === 'severe') {
                return 'Kondisi setelah penanganan belum konsisten dengan perangkat utuh yang siap digunakan kembali. Perbarui hasil pemeriksaan setelah proses perbaikan/refurbish selesai.';
            }
        }

        if (in_array($decision, ['REUSED', 'DONATED'], true)) {
            if ($repairFeasible === 'no' || !in_array($power, ['normal', 'partial'], true) || $damage === 'severe') {
                return 'Barang belum tercatat layak dipertahankan sebagai perangkat utuh. Jalur guna ulang/donasi hanya boleh ditutup jika barang benar-benar aman dan layak digunakan.';
            }
        }

        if ($decision === 'TRANSFER_REUSE_DONATION') {
            if ($repairFeasible === 'no' || !in_array($power, ['normal', 'partial'], true) || $damage === 'severe') {
                return 'Barang belum tercatat layak disalurkan. Jika tidak layak sebagai perangkat utuh, arahkan ke pemulihan komponen/material atau penanganan khusus sesuai kondisi.';
            }
        }

        if ($decision === 'PARTS_RECOVERED' && !in_array($recoveryPotential, ['components', 'both', 'unknown'], true)) {
            return 'Pemeriksaan tidak mencatat potensi komponen yang dapat dipulihkan. Sesuaikan hasil pemeriksaan atau pilih pemulihan material bila itu yang benar-benar dilakukan.';
        }

        if ($decision === 'RECOVERY_CONFIRMED' && !in_array($recoveryPotential, ['materials', 'both', 'unknown'], true)) {
            return 'Pemeriksaan tidak mencatat potensi material yang dapat dipulihkan. Sesuaikan hasil pemeriksaan sebelum menutup pemulihan material.';
        }

        if (
            $decision === 'TRANSFER_SPECIAL_HANDLING'
            && !$hazard
            && $asset->preliminary_path !== 'SPECIAL_HANDLING'
            && !(bool) $asset->category?->special_handling_possible
        ) {
            return 'Belum ada kondisi yang menunjukkan kebutuhan Penanganan Khusus. Gunakan jalur tersebut hanya bila memang terdapat risiko yang relevan.';
        }

        if (in_array($decision, ['PARTS_RECOVERED', 'RECOVERY_CONFIRMED', 'TRANSFER_RECOVERY'], true) && $repairFeasible === 'yes') {
            return $asset->handover_type === 'donation'
                ? 'Barang masih dinyatakan layak dipertahankan sebagai perangkat utuh. Selesaikan perbaikan/refurbish lalu arahkan ke Guna Ulang & Donasi, kecuali pemeriksaan berubah karena barang memang tidak layak.'
                : 'Barang masih dinyatakan layak dipertahankan sebagai perangkat utuh. Jangan langsung menurunkannya ke pemulihan komponen/material sebelum jalur perbaikan/refurbish dinyatakan tidak layak.';
        }

        return null;
    }

    public function repairRuledOut(Asset $asset): bool
    {
        $assessments = $asset->relationLoaded('assessments')
            ? $asset->assessments->where('assessment_type', 'partner')->sortByDesc('id')
            : $asset->assessments()->where('assessment_type', 'partner')->latest('id')->get(['id', 'answers_json']);

        foreach ($assessments as $assessment) {
            $answers = (array) ($assessment->answers_json ?? []);
            $value = $answers['repair_feasible'] ?? 'unknown';
            if (in_array($value, ['yes', 'no'], true)) {
                return $value === 'no';
            }
        }

        return false;
    }

    /**
     * Ketersediaan pilihan hasil/langkah berdasarkan jawaban pemeriksaan yang sedang diisi.
     * Method ini menjadi sumber kebenaran yang sama untuk UI dinamis dan validasi saat submit,
     * sehingga opsi yang terlihat di layar tidak bertentangan dengan guard backend.
     *
     * @return array<string,array{allowed:bool,message:?string}>
     */
    /**
     * Guard lintas tahap agar barang tidak diping-pong kembali ke layanan yang
     * sudah secara eksplisit dinyatakan tidak layak pada pemeriksaan sebelumnya.
     */
    public function transferCapabilityConflict(Asset $asset, string $capability): ?string
    {
        if ($capability === 'repair' && $this->repairRuledOut($asset)) {
            return 'Jalur Perbaikan sudah dinyatakan tidak layak pada pemeriksaan sebelumnya. Lanjutkan penanganan di layanan saat ini atau pilih Pemulihan/Penanganan Khusus sesuai kondisi nyata; jangan mengirim barang kembali ke Perbaikan.';
        }

        return null;
    }

    public function decisionAvailability(Asset $asset, PartnerProfile $profile, array $answers): array
    {
        $availability = [];

        foreach ($this->allowedDecisionCodes($asset, $profile) as $decision) {
            $conflict = $this->assessmentConflictMessage($asset, $profile, $decision, $answers);
            $availability[$decision] = [
                'allowed' => $conflict === null,
                'message' => $conflict,
            ];
        }

        return $availability;
    }

    /**
     * Petunjuk singkat untuk operator berdasarkan kondisi yang baru dicatat.
     * Petunjuk tidak menetapkan outcome; keputusan final tetap harus mencerminkan proses nyata.
     */
    public function assessmentGuidance(Asset $asset, PartnerProfile $profile, array $answers): string
    {
        $power = $answers['power_status'] ?? 'unknown';
        $damage = $answers['damage_level'] ?? 'unknown';
        $repairFeasible = $answers['repair_feasible'] ?? 'unknown';
        $hazard = ($answers['hazard_found'] ?? 'unknown') === 'yes'
            || ($answers['refrigerant_risk'] ?? 'unknown') === 'yes'
            || ($answers['power_safety'] ?? null) === 'hazard'
            || ($answers['hygiene_condition'] ?? null) === 'unsafe';
        $recoveryPotential = $answers['recovery_potential'] ?? 'unknown';
        $caps = $this->approvedCapabilities($profile);
        $usableNow = in_array($power, ['normal', 'partial'], true) && $damage !== 'severe';

        if ($hazard) {
            return in_array('special_handling', $caps, true)
                ? 'Ada kondisi yang memerlukan penanganan khusus. Mitra Anda dapat menanganinya di sini; pilih selesai hanya setelah prosedur penanganan khusus benar-benar tuntas.'
                : 'Ada kondisi yang memerlukan penanganan khusus dan layanan tersebut tidak dimiliki mitra Anda. Alihkan barang ke Mitra Penanganan Khusus; jangan menutupnya sebagai repair, reuse, atau recovery biasa.';
        }

        if ($repairFeasible === 'no') {
            if (in_array('recovery', $caps, true)) {
                if (in_array($recoveryPotential, ['components', 'both'], true)) {
                    return 'Perangkat tidak layak dipertahankan sebagai barang utuh, tetapi masih ada komponen bernilai. Mitra Anda dapat menyelesaikan pemulihan komponen di sini; transfer tidak diperlukan jika proses memang dapat dituntaskan oleh mitra ini.';
                }
                return 'Perangkat tidak layak dipertahankan sebagai barang utuh. Mitra Anda memiliki layanan pemulihan, sehingga komponen/material dapat diproses dan diselesaikan di sini sesuai hasil nyata.';
            }

            return 'Perangkat tidak layak dipertahankan sebagai barang utuh dan mitra Anda tidak memiliki layanan pemulihan. Alihkan ke Mitra Pemulihan Komponen/Material yang sesuai.';
        }

        if (!$usableNow) {
            return 'Perangkat belum layak disalurkan untuk digunakan kembali. Lanjutkan pemeriksaan/perbaikan jika masih realistis; jika ternyata tidak layak sebagai barang utuh, arahkan ke pemulihan komponen/material.';
        }

        if ($asset->handover_type === 'donation') {
            return 'Perangkat masih berpotensi dipertahankan sebagai barang utuh. Karena tujuan warga adalah donasi, selesaikan perbaikan/refurbish bila diperlukan lalu teruskan ke jalur Guna Ulang & Donasi.';
        }

        if ($repairFeasible === 'yes' && in_array('repair', $caps, true)) {
            return 'Perangkat tercatat layak dipertahankan melalui perbaikan/refurbish. Pilih hasil selesai hanya setelah pekerjaan benar-benar tuntas dan perangkat aman untuk masuk kembali ke jalur penggunaan.';
        }

        return 'Pilihan langkah sudah disaring berdasarkan pemeriksaan dan kemampuan mitra. Tutup hasil hanya untuk proses yang benar-benar sudah selesai; jika layanan lanjutan tidak dimiliki, gunakan pengalihan.';
    }

    public function invalidDecisionMessage(Asset $asset, string $decision): string
    {
        if ($asset->handover_type === 'donation' && $decision === 'REPAIRED') {
            return 'Warga memilih tujuan Donasi. Perbaikan/refurbish adalah tahap antara, bukan hasil akhir. Setelah perangkat layak, lanjutkan ke jalur Guna Ulang & Donasi; jika tidak layak sebagai barang utuh, pilih pemulihan yang sesuai.';
        }

        return 'Pilihan tersebut tidak sesuai dengan tujuan penyerahan atau layanan mitra saat ini. Pilih hasil yang benar-benar sudah terjadi atau alihkan ke mitra dengan layanan yang sesuai.';
    }


    public function statusForFinalDecision(string $decision): string
    {
        return match ($decision) {
            'REUSED' => 'reused',
            'REPAIRED' => 'repaired',
            'DONATED' => 'donated',
            'PARTS_RECOVERED' => 'parts_recovered',
            'RECOVERY_CONFIRMED' => 'recovery_confirmed',
            'SPECIAL_HANDLING_COMPLETED' => 'special_handling_completed',
            'RETURNED_TO_OWNER' => 'returned', // histori legacy saja
            'UNVERIFIED_FINAL_TREATMENT' => 'unverified',
            default => strtolower($decision),
        };
    }

    public function handoverGoalTitle(Asset $asset): string
    {
        return match ($asset->handover_type) {
            'donation' => 'Tujuan warga: Donasi jika kondisi memungkinkan',
            'sale' => 'Tujuan warga: Penyerahan dengan penawaran nilai',
            'free_handover' => 'Tujuan warga: Serahkan tanpa kompensasi ke jalur sirkular yang sesuai',
            default => 'Tujuan penyerahan belum dipilih',
        };
    }

    public function handoverGoalHelp(Asset $asset): string
    {
        return match ($asset->handover_type) {
            'donation' => 'Donasi adalah tujuan akhir bila barang aman dan layak. Jika perlu perbaikan/refurbish, proses itu menjadi tahap antara. Jika tidak layak sebagai barang utuh, lanjutkan ke pemulihan komponen/material atau penanganan khusus sesuai kondisi.',
            'sale' => 'Penawaran nilai mengatur kesepakatan penyerahan, bukan menentukan hasil sirkular. Hasil akhir tetap mengikuti kondisi fisik dan proses yang benar-benar dilakukan.',
            'free_handover' => 'Tidak ada kompensasi kepada warga. Mitra tetap harus mencatat hasil penanganan yang benar-benar terjadi.',
            default => 'Hasil akhir ditentukan setelah pemeriksaan fisik dan tidak boleh dibuat hanya dari perkiraan.',
        };
    }

    /**
     * Menentukan layanan yang harus dimiliki mitra tujuan berdasarkan hasil assessment transfer terakhir.
     */
    public function requiredTransferCapability(Asset $asset, ?AssetAssessment $assessment = null, ?PartnerProfile $currentProfile = null): string
    {
        $decision = (string) ($assessment?->result_path ?? '');
        if ($cap = $this->transferCapabilityForDecision($decision)) {
            return $cap;
        }

        // Kompatibilitas data v1.0.14/v1.0.15 yang masih memakai TRANSFER_REQUIRED generik.
        $answers = (array) ($assessment?->answers_json ?? []);
        $currentCaps = $currentProfile ? $this->approvedCapabilities($currentProfile) : [];

        if (($answers['repair_feasible'] ?? null) === 'no') {
            return 'recovery';
        }
        if ($asset->handover_type === 'donation' && !in_array('reuse_donation', $currentCaps, true)) {
            return 'reuse_donation';
        }
        if ($asset->preliminary_path === 'SPECIAL_HANDLING' && !in_array('special_handling', $currentCaps, true)) {
            return 'special_handling';
        }

        $initial = $this->initialCapability($asset);
        if (
            !in_array($initial, $currentCaps, true)
            && !in_array($initial, ['collection', 'pickup'], true)
            && !($initial === 'repair' && $this->repairRuledOut($asset))
        ) {
            return $initial;
        }

        if (in_array('repair', $currentCaps, true)) {
            return 'recovery';
        }
        if (in_array('special_handling', $currentCaps, true)) {
            return 'recovery';
        }

        return 'recovery';
    }
}
