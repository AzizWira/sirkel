<?php

namespace Database\Seeders;

use App\Models\{CircularRule, DeviceCategory, DeviceGroup, Question, QuestionnaireTemplate, QuestionOption, Region, SystemSetting};
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['mobile-computing', 'Ponsel & Komputasi', 'Ponsel, komputer, perangkat mobile, dan layar komputer'],
            ['accessories-power', 'Aksesori & Daya', 'Aksesori elektronik, sumber daya, baterai, kabel, dan perangkat proteksi daya'],
            ['small-household', 'Elektronik Rumah Tangga Kecil', 'Peralatan elektronik rumah tangga berukuran kecil dan mudah dipindahkan'],
            ['large-household', 'Elektronik Rumah Tangga Besar', 'Peralatan rumah tangga berukuran besar termasuk perangkat pendingin dan pencucian'],
            ['office-peripheral', 'Perangkat Kantor & Periferal', 'Printer, scanner, periferal komputer, jaringan, dan perangkat presentasi'],
            ['audio-video', 'Audio, Video & Kamera', 'Televisi, perangkat audio, kamera, dan perangkat pemantauan'],
            ['gaming-entertainment', 'Gaming & Hiburan', 'Konsol permainan dan aksesori gaming elektronik'],
            ['personal-care', 'Perawatan Pribadi & Kesehatan', 'Perangkat elektronik perawatan pribadi dan alat kesehatan rumah tangga'],
            ['lighting-tools', 'Pencahayaan & Perkakas Elektrik', 'Lampu elektronik dan perkakas listrik rumah tangga'],
            ['other-electronics', 'Elektronik Lainnya', 'Perangkat elektronik yang kelompoknya belum diketahui atau belum tersedia'],
        ];

        $map = [];
        foreach ($groups as $i => $group) {
            $map[$group[0]] = DeviceGroup::updateOrCreate(
                ['code' => $group[0]],
                ['name' => $group[1], 'description' => $group[2], 'sort_order' => $i + 1, 'active' => true]
            );
        }

        // v1.0.20 used a separate group for one generic category. Keep the row for history,
        // but move the category into the real household hierarchy and hide the obsolete group.
        DeviceGroup::where('code', 'other-small')->update(['active' => false]);

        $categories = [
            // Ponsel & komputasi
            ['mobile-computing', 'smartphone', 'Smartphone', false, true],
            ['mobile-computing', 'feature-phone', 'Feature Phone / HP Tombol', false, true],
            ['mobile-computing', 'tablet', 'Tablet', false, true],
            ['mobile-computing', 'laptop', 'Laptop', false, true],
            ['mobile-computing', 'desktop-pc', 'Komputer Desktop / PC', false, false],
            ['mobile-computing', 'mini-pc', 'Mini PC', false, false],
            ['mobile-computing', 'monitor', 'Monitor Komputer', false, false],
            ['mobile-computing', 'smartwatch', 'Smartwatch / Jam Pintar', false, true],
            ['mobile-computing', 'e-reader', 'E-Reader', false, true],
            ['mobile-computing', 'other-mobile-computing', 'Ponsel / Komputasi Lainnya', false, true],

            // Aksesori & daya
            ['accessories-power', 'charger', 'Charger', true, false],
            ['accessories-power', 'cable', 'Kabel Elektronik', true, false],
            ['accessories-power', 'power-adapter', 'Adaptor Daya', true, false],
            ['accessories-power', 'powerbank', 'Powerbank', false, true],
            ['accessories-power', 'battery', 'Baterai', true, true],
            ['accessories-power', 'ups', 'UPS', false, true],
            ['accessories-power', 'voltage-stabilizer', 'Stabilizer / AVR', false, false],
            ['accessories-power', 'extension-cord', 'Terminal / Kabel Rol', true, false],
            ['accessories-power', 'other-accessories-power', 'Aksesori / Perangkat Daya Lainnya', false, true],

            // Rumah tangga kecil
            ['small-household', 'blender', 'Blender', false, false],
            ['small-household', 'mixer', 'Mixer', false, false],
            ['small-household', 'iron', 'Setrika', false, false],
            ['small-household', 'rice-cooker-small', 'Rice Cooker', false, false],
            ['small-household', 'electric-kettle', 'Ketel Listrik', false, false],
            ['small-household', 'toaster', 'Pemanggang Roti', false, false],
            ['small-household', 'small-fan', 'Kipas Kecil / Kipas Meja', false, false],
            ['small-household', 'hair-dryer', 'Pengering Rambut', false, false],
            ['small-household', 'air-fryer', 'Air Fryer', false, false],
            ['small-household', 'coffee-maker', 'Mesin Pembuat Kopi', false, false],
            ['small-household', 'food-chopper', 'Food Chopper', false, false],
            ['small-household', 'electric-stove', 'Kompor Listrik / Induksi Portabel', false, false],
            ['small-household', 'other-small-electronics', 'Elektronik Rumah Tangga Kecil Lainnya', false, true],

            // Rumah tangga besar
            ['large-household', 'refrigerator', 'Kulkas', false, true],
            ['large-household', 'freezer', 'Freezer', false, true],
            ['large-household', 'washing-machine', 'Mesin Cuci', false, false],
            ['large-household', 'clothes-dryer', 'Mesin Pengering Pakaian', false, false],
            ['large-household', 'air-conditioner', 'AC / Air Conditioner', false, true],
            ['large-household', 'water-dispenser', 'Dispenser Air Elektrik', false, false],
            ['large-household', 'water-heater', 'Pemanas Air Elektrik', false, false],
            ['large-household', 'microwave', 'Microwave', false, false],
            ['large-household', 'electric-oven', 'Oven Listrik', false, false],
            ['large-household', 'vacuum-cleaner', 'Vacuum Cleaner', false, false],
            ['large-household', 'large-fan', 'Kipas Berdiri / Kipas Besar', false, false],
            ['large-household', 'other-large-household', 'Elektronik Rumah Tangga Besar Lainnya', false, true],

            // Kantor & periferal
            ['office-peripheral', 'small-printer', 'Printer', false, false],
            ['office-peripheral', 'scanner', 'Scanner', false, false],
            ['office-peripheral', 'keyboard', 'Keyboard', true, false],
            ['office-peripheral', 'mouse', 'Mouse', true, false],
            ['office-peripheral', 'modem-router', 'Modem / Router / Access Point', false, false],
            ['office-peripheral', 'projector', 'Proyektor', false, false],
            ['office-peripheral', 'webcam', 'Webcam', false, false],
            ['office-peripheral', 'external-storage', 'Hard Disk / SSD Eksternal / Flashdisk', true, false],
            ['office-peripheral', 'other-office-peripheral', 'Perangkat Kantor / Periferal Lainnya', false, false],

            // Audio, video & kamera
            ['audio-video', 'television', 'Televisi / Smart TV', false, false],
            ['audio-video', 'set-top-box', 'Set Top Box / TV Box', false, false],
            ['audio-video', 'speaker', 'Speaker / Soundbar', false, false],
            ['audio-video', 'headphones', 'Headphone / Headset', true, true],
            ['audio-video', 'earphones', 'Earphone / TWS', true, true],
            ['audio-video', 'radio', 'Radio', false, false],
            ['audio-video', 'camera', 'Kamera Digital', false, true],
            ['audio-video', 'cctv', 'Kamera CCTV', false, false],
            ['audio-video', 'video-player', 'DVD / Media Player', false, false],
            ['audio-video', 'other-audio-video', 'Perangkat Audio / Video Lainnya', false, true],

            // Gaming
            ['gaming-entertainment', 'game-console', 'Konsol Game', false, false],
            ['gaming-entertainment', 'handheld-console', 'Konsol Game Genggam', false, true],
            ['gaming-entertainment', 'game-controller', 'Controller / Gamepad', true, true],
            ['gaming-entertainment', 'other-gaming-electronics', 'Perangkat Gaming Lainnya', false, true],

            // Perawatan pribadi & kesehatan
            ['personal-care', 'electric-shaver', 'Alat Cukur Elektrik', false, true],
            ['personal-care', 'hair-clipper', 'Hair Clipper / Trimmer', false, true],
            ['personal-care', 'electric-toothbrush', 'Sikat Gigi Elektrik', false, true],
            ['personal-care', 'digital-scale', 'Timbangan Digital', false, true],
            ['personal-care', 'digital-thermometer', 'Termometer Digital', true, true],
            ['personal-care', 'blood-pressure-monitor', 'Tensimeter Digital', false, true],
            ['personal-care', 'other-personal-care', 'Elektronik Perawatan Pribadi / Kesehatan Lainnya', false, true],

            // Pencahayaan & perkakas
            ['lighting-tools', 'led-lamp', 'Lampu LED / Lampu Elektronik', true, false],
            ['lighting-tools', 'emergency-lamp', 'Lampu Darurat Isi Ulang', false, true],
            ['lighting-tools', 'electric-drill', 'Bor Listrik', false, false],
            ['lighting-tools', 'soldering-iron', 'Solder Listrik', false, false],
            ['lighting-tools', 'electric-screwdriver', 'Obeng Elektrik', false, true],
            ['lighting-tools', 'other-lighting-tools', 'Pencahayaan / Perkakas Elektrik Lainnya', false, true],

            // Tidak tahu kelompoknya
            ['other-electronics', 'uncategorized-electronics', 'Elektronik Lainnya / Belum Tahu Kategorinya', false, true],
        ];

        $categoryMap = [];
        foreach ($categories as $i => $category) {
            $categoryMap[$category[1]] = DeviceCategory::updateOrCreate(
                ['code' => $category[1]],
                [
                    'device_group_id' => $map[$category[0]]->id,
                    'name' => $category[2],
                    'supports_batch' => $category[3],
                    'special_handling_possible' => $category[4],
                    'active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $generic = QuestionnaireTemplate::updateOrCreate(
            ['code' => 'generic-small-electronics'],
            ['name' => 'Cek Kondisi Elektronik', 'device_category_id' => null, 'device_group_id' => null, 'audience' => 'citizen', 'active' => true]
        );

        $this->question($generic, 'power_status', 'Apakah barang/perangkat ini masih berfungsi?', 'single', 1, [
            ['normal', 'Ya, berfungsi normal'],
            ['partial', 'Berfungsi sebagian / bermasalah'],
            ['off', 'Tidak berfungsi'],
            ['unknown', 'Tidak tahu'],
        ], 'Yang dimaksud “berfungsi” adalah barang masih dapat menjalankan fungsi utamanya. Pilih “Berfungsi normal” jika dapat digunakan sebagaimana mestinya, “Berfungsi sebagian” jika masih bekerja tetapi ada masalah, dan “Tidak berfungsi” jika fungsi utamanya tidak dapat digunakan. Jika belum pernah mencoba atau belum yakin, pilih “Tidak tahu”.');

        $this->question($generic, 'damage_level', 'Bagaimana kondisi fisik barang secara umum?', 'single', 2, [
            ['none', 'Tidak ada kerusakan berarti'],
            ['minor', 'Kerusakan ringan'],
            ['moderate', 'Kerusakan sedang'],
            ['severe', 'Kerusakan berat'],
            ['unknown', 'Tidak tahu'],
        ], 'Nilai kerusakan yang terlihat atau terasa tanpa perlu membongkar barang. Kerusakan ringan misalnya lecet atau bagian kecil longgar, sedang berarti mulai mengganggu penggunaan, sedangkan berat berarti kerusakan besar, pecah, terbakar, atau kondisi fisik yang membuat barang tidak aman digunakan. Jika ragu, pilih “Tidak tahu”.');

        $this->question($generic, 'hazard_sign', 'Apakah ada tanda bahaya pada barang?', 'single', 3, [
            ['yes', 'Ya, ada tanda bahaya'],
            ['no', 'Tidak terlihat'],
            ['unknown', 'Tidak tahu'],
        ], 'Tanda bahaya dapat berupa bekas terbakar atau meleleh, percikan, kabel terkelupas/terbuka, bodi atau baterai menggembung, bau menyengat, panas tidak wajar, atau cairan yang tidak semestinya. Jangan menyalakan atau membongkar barang hanya untuk memeriksa. Jika Anda tidak yakin, pilih “Tidak tahu”.');

        $this->question($generic, 'technician_result', 'Apakah kondisi barang ini pernah diperiksa teknisi?', 'single', 4, [
            ['repairable', 'Ya, masih bisa diperbaiki'],
            ['not_repairable', 'Ya, dinyatakan tidak layak diperbaiki'],
            ['not_checked', 'Belum pernah'],
            ['unknown', 'Tidak tahu'],
        ], 'Pertanyaan ini menanyakan apakah kondisi barang pernah dinilai oleh teknisi sebelumnya, bukan sekadar pernah dibawa ke tempat servis. Jika teknisi menyatakan masih dapat diperbaiki, pilih pilihan pertama. Jika teknisi menyatakan perbaikan tidak layak atau tidak memungkinkan, pilih pilihan kedua. Jika belum pernah diperiksa, pilih “Belum pernah”.');

        $this->question($generic, 'user_intent', 'Apa yang paling Anda inginkan untuk barang ini?', 'single', 5, [
            ['reuse', 'Dimanfaatkan kembali jika masih memungkinkan'],
            ['donate', 'Disalurkan/donasikan jika masih layak'],
            ['safe_handover', 'Saya ingin menyerahkan/membuangnya dengan aman'],
            ['recycle', 'Diarahkan ke pemulihan material jika sudah tidak layak'],
            ['unsure', 'Bantu rekomendasikan'],
        ], 'Pilih berdasarkan tujuan Anda saat menyerahkan barang. Anda tidak perlu mengetahui metode penanganan teknis yang tepat. Jika hanya ingin barang tidak lagi berada di rumah dan ditangani dengan aman, pilih “Saya ingin menyerahkan/membuangnya dengan aman”. SIRKEL tetap menentukan jalur sirkular berdasarkan kondisi barang agar barang yang masih bernilai tidak langsung menjadi limbah.');

        $this->question($generic, 'notes', 'Jelaskan gejala atau kondisi lain yang ingin diketahui mitra.', 'text', 6, [], 'Tuliskan hal lain yang Anda ketahui mengenai kondisi barang, misalnya “blender berbunyi tetapi pisau tidak berputar”, “charger hanya berfungsi jika kabel ditekuk”, atau “sudah lama tidak digunakan”. Tidak perlu memakai istilah teknis. Jika tidak ada informasi tambahan, kolom ini boleh dikosongkan.');

        $dataBearingCodes = [
            'smartphone', 'feature-phone', 'tablet', 'laptop', 'desktop-pc', 'mini-pc', 'smartwatch', 'e-reader',
            'external-storage', 'camera', 'game-console', 'handheld-console', 'other-mobile-computing',
        ];
        $batteryAwareCodes = ['smartphone', 'feature-phone', 'tablet', 'laptop', 'smartwatch', 'e-reader', 'camera', 'handheld-console'];

        foreach ($dataBearingCodes as $code) {
            $template = QuestionnaireTemplate::updateOrCreate(
                ['code' => $code.'-assessment'],
                ['name' => 'Cek Kondisi '.$categoryMap[$code]->name, 'device_category_id' => $categoryMap[$code]->id, 'device_group_id' => null, 'audience' => 'citizen', 'active' => true]
            );
            $this->cloneQuestions($generic, $template);
            $order = 7;
            if (in_array($code, $batteryAwareCodes, true)) {
                $this->question($template, 'battery_swollen', 'Apakah baterai/perangkat terlihat menggembung atau bentuknya berubah?', 'single', $order++, [
                    ['yes', 'Ya'], ['no', 'Tidak'], ['unknown', 'Tidak tahu'],
                ], 'Baterai menggembung dapat membuat bodi, layar, atau penutup terangkat. Jangan menekan, mengisi daya, atau membongkar perangkat jika ada tanda tersebut. Jika tidak dapat memastikan, pilih “Tidak tahu”.');
            }
            $this->question($template, 'personal_data', 'Apakah perangkat masih menyimpan data pribadi?', 'single', $order, [
                ['yes', 'Ya'], ['no', 'Sudah dibersihkan'], ['unknown', 'Tidak tahu'],
            ], 'Data pribadi dapat berupa foto, dokumen, akun, kontak, rekaman, atau file lain yang masih tersimpan. Jika perangkat masih dapat diakses dan datanya belum dihapus, pilih “Ya”. Jika sudah melakukan reset/penghapusan data, pilih “Sudah dibersihkan”. Jika perangkat tidak dapat diperiksa atau Anda tidak yakin, pilih “Tidak tahu”.');
        }

        foreach (['battery', 'powerbank', 'ups'] as $code) {
            $template = QuestionnaireTemplate::updateOrCreate(
                ['code' => $code.'-safety'],
                ['name' => 'Cek Kondisi '.$categoryMap[$code]->name, 'device_category_id' => $categoryMap[$code]->id, 'device_group_id' => null, 'audience' => 'citizen', 'active' => true]
            );
            $this->question($template, 'power_status', 'Apakah baterai/perangkat daya ini masih berfungsi?', 'single', 1, [
                ['normal', 'Ya, berfungsi normal'], ['partial', 'Berfungsi sebagian / tidak stabil'], ['off', 'Tidak berfungsi'], ['unknown', 'Tidak tahu'],
            ], 'Pilih berdasarkan pemakaian terakhir yang Anda ketahui. “Berfungsi sebagian / tidak stabil” dapat berarti daya cepat habis, pengisian terputus-putus, atau fungsi tidak konsisten. Jangan mencoba menyalakan, mengisi, menekan, atau membongkar perangkat daya yang tampak rusak hanya untuk menjawab pertanyaan ini.');
            $this->question($template, 'hazard_sign', 'Apakah ada tanda bahaya seperti terbakar, meleleh, panas tidak wajar, atau bau menyengat?', 'single', 2, [
                ['yes', 'Ya, ada tanda bahaya'], ['no', 'Tidak terlihat'], ['unknown', 'Tidak tahu'],
            ], 'Periksa hanya dari luar. Jangan mengisi daya atau menyalakan perangkat jika ada bekas terbakar, bagian meleleh, panas tidak wajar, percikan, atau bau menyengat. Jika tidak yakin, pilih “Tidak tahu”.');
            $this->question($template, 'battery_swollen', 'Apakah baterai terlihat menggembung atau bentuk perangkat berubah?', 'single', 3, [
                ['yes', 'Ya'], ['no', 'Tidak'], ['unknown', 'Tidak tahu'],
            ], 'Baterai menggembung dapat terlihat lebih tebal, berubah bentuk, atau mendorong penutup perangkat. Jangan menekan, menusuk, membuka, atau mencoba meratakan baterai. Jika bentuknya sulit dipastikan, pilih “Tidak tahu”.');
            $this->question($template, 'battery_leaking', 'Apakah terlihat kebocoran, cairan, atau korosi yang tidak biasa?', 'single', 4, [
                ['yes', 'Ya'], ['no', 'Tidak'], ['unknown', 'Tidak tahu'],
            ], 'Perhatikan hanya dari luar tanpa membongkar barang. Tanda yang dimaksud antara lain cairan, kerak/korosi tidak biasa, bau menyengat, atau bekas kebocoran. Jika ada tanda tersebut, hindari kontak langsung dan pilih “Ya”. Jika tidak dapat memastikan, pilih “Tidak tahu”.');
            $this->question($template, 'damage_level', 'Bagaimana kondisi fisiknya secara umum?', 'single', 5, [
                ['none', 'Normal'], ['minor', 'Kerusakan ringan'], ['moderate', 'Kerusakan sedang'], ['severe', 'Kerusakan berat'], ['unknown', 'Tidak tahu'],
            ], 'Nilai kondisi yang terlihat dari luar. Kerusakan berat mencakup bentuk berubah parah, pecah, bekas terbakar, atau kerusakan yang membuat Anda ragu untuk menyentuh/menggunakannya. Jangan melakukan pengujian tambahan jika ada tanda risiko.');
            $this->question($template, 'user_intent', 'Apa yang paling Anda inginkan untuk barang ini?', 'single', 6, [
                ['safe_handover', 'Saya ingin menyerahkan/membuangnya dengan aman'],
                ['recycle', 'Serahkan untuk pemulihan yang sesuai'],
                ['unsure', 'Bantu rekomendasikan'],
            ], 'Jika tujuan utama Anda hanya agar baterai/perangkat daya tidak dibuang sembarangan, pilih “Saya ingin menyerahkan/membuangnya dengan aman”. Jika Anda memang menginginkan pemulihan material, pilih pilihan tersebut. Apa pun pilihan Anda, indikasi risiko seperti baterai menggembung atau bocor tetap diprioritaskan ke penanganan khusus.');
        }

        foreach (['refrigerator', 'freezer', 'air-conditioner'] as $code) {
            $template = QuestionnaireTemplate::updateOrCreate(
                ['code' => $code.'-assessment'],
                ['name' => 'Cek Kondisi '.$categoryMap[$code]->name, 'device_category_id' => $categoryMap[$code]->id, 'device_group_id' => null, 'audience' => 'citizen', 'active' => true]
            );
            $this->cloneQuestions($generic, $template);
            $this->question($template, 'cooling_leak', 'Apakah terlihat kebocoran atau kerusakan pada pipa/sistem pendingin?', 'single', 7, [
                ['yes', 'Ya'], ['no', 'Tidak terlihat'], ['unknown', 'Tidak tahu'],
            ], 'Periksa hanya dari luar. Tanda yang perlu diperhatikan antara lain pipa rusak, cairan/oli yang tidak biasa di sekitar sistem pendingin, suara desis, atau kerusakan akibat benturan pada bagian pendingin. Jangan membongkar pipa atau sistem pendingin.');
        }


        // v1.0.28 — pemeriksaan mitra memakai template berlapis yang dapat dikelola admin.
        // Pertanyaan umum berlaku ke semua barang; template kelompok/kategori hanya menyimpan
        // tambahan atau override agar perubahan master tidak perlu diduplikasi ke banyak kategori.
        $partnerGeneric = QuestionnaireTemplate::updateOrCreate(
            ['code' => 'partner-generic-assessment'],
            [
                'name' => 'Pemeriksaan Mitra — Umum',
                'device_category_id' => null,
                'device_group_id' => null,
                'audience' => 'partner',
                'active' => true,
            ]
        );
        // Bersihkan pertanyaan lama/hasil kloning versi sebelumnya. Template partner
        // v1.0.28 disusun berlapis oleh QuestionnaireService.
        $partnerGeneric->questions()->delete();
        $this->question($partnerGeneric, 'power_status', 'Apakah fungsi utama perangkat masih bekerja?', 'single', 1, [
            ['normal', 'Ya, berfungsi normal'], ['partial', 'Ya, tetapi bermasalah'], ['off', 'Tidak berfungsi'], ['unknown', 'Belum dapat dipastikan'],
        ], 'Nilai fungsi utama perangkat berdasarkan pemeriksaan fisik yang aman. Jangan melakukan pengujian yang meningkatkan risiko hanya untuk mengisi form.');
        $this->question($partnerGeneric, 'damage_level', 'Seberapa berat kerusakan perangkat secara keseluruhan?', 'single', 2, [
            ['none', 'Tidak ada kerusakan berarti'], ['minor', 'Ringan'], ['moderate', 'Sedang'], ['severe', 'Berat'], ['unknown', 'Belum dapat dipastikan'],
        ], 'Gunakan tingkat kerusakan berdasarkan kondisi aktual, bukan perkiraan warga. Kerusakan berat berarti perangkat tidak aman atau tidak realistis dipertahankan sebagai barang utuh tanpa pekerjaan besar.');
        $this->question($partnerGeneric, 'repair_feasible', 'Apakah perangkat masih layak dipertahankan sebagai barang utuh melalui perbaikan/refurbish?', 'single', 3, [
            ['yes', 'Ya, layak diperbaiki / direfurbish'], ['no', 'Tidak layak sebagai barang utuh'], ['unknown', 'Belum dapat dipastikan'],
        ], 'Pertanyaan ini menilai kelayakan mempertahankan perangkat sebagai barang utuh. Jika tidak layak, komponen atau materialnya masih dapat memiliki nilai dan dapat masuk jalur pemulihan.');
        $this->question($partnerGeneric, 'hazard_found', 'Apakah ditemukan kondisi yang memerlukan penanganan khusus?', 'single', 4, [
            ['yes', 'Ya'], ['no', 'Tidak'], ['unknown', 'Belum dapat dipastikan'],
        ], 'Contohnya baterai menggembung/bocor, bekas terbakar, risiko listrik, cairan berbahaya, atau kondisi lain yang tidak aman ditangani dengan proses biasa.');
        $this->question($partnerGeneric, 'recovery_potential', 'Jika perangkat tidak dipertahankan sebagai barang utuh, apa yang masih dapat dipulihkan?', 'single', 5, [
            ['components', 'Komponen yang masih bernilai guna'], ['materials', 'Material seperti logam/plastik/kabel'], ['both', 'Komponen dan material'], ['none', 'Belum terlihat nilai pemulihan'], ['unknown', 'Belum dapat dipastikan'],
        ], 'Pilih berdasarkan hasil pemeriksaan dan kemampuan pemulihan yang tersedia. Ini bukan kewajiban membongkar barang pada tahap pemeriksaan.');

        $partnerGroupExtra = [
            'mobile-computing' => ['data_condition', 'Bagaimana kondisi bagian penyimpanan/data perangkat?', [
                ['safe', 'Sudah aman / tidak ada data yang perlu ditangani'], ['needs_handling', 'Masih perlu penanganan data'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Periksa hanya sesuai prosedur mitra. Jangan membuka atau menyalin data pribadi warga di luar kebutuhan penanganan perangkat.'],
            'accessories-power' => ['power_safety', 'Bagaimana kondisi kelistrikan/baterainya?', [
                ['safe', 'Tidak ditemukan indikasi bahaya'], ['unstable', 'Tidak stabil / perlu pemeriksaan lanjutan'], ['hazard', 'Ada indikasi bahaya'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Perhatikan panas tidak wajar, kebocoran, bentuk berubah, kabel terbuka, atau bekas terbakar.'],
            'small-household' => ['mechanical_condition', 'Bagaimana kondisi bagian mekanik/elektrik utama?', [
                ['good', 'Masih layak'], ['repairable', 'Bermasalah tetapi masih dapat ditangani'], ['failed', 'Rusak berat / tidak layak'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Nilai bagian utama seperti motor, elemen pemanas, sakelar, atau mekanisme penggerak sesuai jenis perangkat.'],
            'large-household' => ['major_system_condition', 'Bagaimana kondisi sistem utama perangkat?', [
                ['good', 'Masih layak'], ['repairable', 'Bermasalah tetapi masih dapat ditangani'], ['failed', 'Rusak berat / tidak layak'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Nilai sistem utama sesuai jenis barang, misalnya motor, kompresor, pemanas, kontrol, atau mekanisme pencucian.'],
            'office-peripheral' => ['peripheral_function', 'Apakah fungsi periferal/konektivitas utamanya bekerja?', [
                ['normal', 'Normal'], ['partial', 'Sebagian / tidak stabil'], ['off', 'Tidak berfungsi'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Periksa fungsi utama seperti cetak, pindai, konektivitas, input, atau penyimpanan sesuai perangkat.'],
            'audio-video' => ['av_output', 'Bagaimana kondisi keluaran audio/video atau sensornya?', [
                ['normal', 'Normal'], ['partial', 'Sebagian / bermasalah'], ['off', 'Tidak berfungsi'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Sesuaikan dengan jenis perangkat: layar, speaker, kamera, atau keluaran media lainnya.'],
            'gaming-entertainment' => ['gaming_function', 'Apakah fungsi utama permainan/kontrol masih bekerja?', [
                ['normal', 'Normal'], ['partial', 'Sebagian / bermasalah'], ['off', 'Tidak berfungsi'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Nilai fungsi utama konsol/controller tanpa memaksa perangkat yang menunjukkan risiko listrik atau baterai.'],
            'personal-care' => ['hygiene_condition', 'Apakah kondisi perangkat aman dan layak untuk ditangani lebih lanjut?', [
                ['safe', 'Ya'], ['needs_cleaning', 'Perlu pembersihan/dekontaminasi'], ['unsafe', 'Tidak aman'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Perangkat perawatan pribadi dapat memerlukan perhatian kebersihan sebelum proses reuse atau pembongkaran.'],
            'lighting-tools' => ['tool_function', 'Bagaimana kondisi fungsi listrik/gerak utama perangkat?', [
                ['normal', 'Normal'], ['partial', 'Sebagian / bermasalah'], ['off', 'Tidak berfungsi'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Periksa sesuai prosedur keselamatan kerja dan hindari pengujian jika kabel atau bagian listrik terbuka.'],
            'other-electronics' => ['identification_confidence', 'Apakah jenis dan fungsi utama perangkat sudah dapat diidentifikasi?', [
                ['yes', 'Ya'], ['partial', 'Sebagian'], ['no', 'Belum'],
            ], 'Jika jenis perangkat belum jelas, jangan memaksakan outcome. Simpan pemeriksaan atau alihkan ke layanan yang lebih sesuai.'],
        ];

        $partnerGroupTemplates = [];
        foreach ($partnerGroupExtra as $groupCode => [$questionCode, $questionText, $options, $help]) {
            $template = QuestionnaireTemplate::updateOrCreate(
                ['code' => 'partner-'.$groupCode.'-assessment'],
                [
                    'name' => 'Pemeriksaan Mitra — '.$map[$groupCode]->name,
                    'device_category_id' => null,
                    'device_group_id' => $map[$groupCode]->id,
                    'audience' => 'partner',
                    'active' => true,
                ]
            );
            $template->questions()->delete();
            $this->question($template, $questionCode, $questionText, 'single', 6, $options, $help);
            $partnerGroupTemplates[$groupCode] = $template;
        }

        foreach (['refrigerator', 'freezer', 'air-conditioner'] as $code) {
            $template = QuestionnaireTemplate::updateOrCreate(
                ['code' => 'partner-'.$code.'-assessment'],
                [
                    'name' => 'Pemeriksaan Mitra — '.$categoryMap[$code]->name,
                    'device_category_id' => $categoryMap[$code]->id,
                    'device_group_id' => null,
                    'audience' => 'partner',
                    'active' => true,
                ]
            );
            $template->questions()->delete();
            $this->question($template, 'cooling_system_status', 'Bagaimana kondisi sistem pendingin/kompresor?', 'single', 7, [
                ['normal', 'Masih bekerja normal'], ['partial', 'Bekerja tetapi bermasalah'], ['failed', 'Tidak bekerja / rusak berat'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Nilai berdasarkan pemeriksaan yang aman. Tidak perlu membuka sistem refrigeran hanya untuk mengisi form.');
            $this->question($template, 'refrigerant_risk', 'Apakah ada indikasi kebocoran atau risiko pada sistem refrigeran?', 'single', 8, [
                ['yes', 'Ya'], ['no', 'Tidak'], ['unknown', 'Belum dapat dipastikan'],
            ], 'Jika ada indikasi kebocoran, jangan lepaskan refrigeran ke lingkungan. Pilih jalur penanganan khusus jika proses yang dibutuhkan berada di luar kemampuan mitra saat ini.');
        }

        CircularRule::where('name', 'Donasi untuk perangkat layak sesuai prioritas warga')->delete();

        $rules = [
            ['Penanganan khusus untuk tanda bahaya umum', 1, ['hazard_sign' => 'yes'], 'SPECIAL_HANDLING', 'Ada tanda bahaya yang perlu diperiksa oleh mitra dengan penanganan yang sesuai.'],
            ['Penanganan khusus untuk baterai berisiko', 2, ['battery_swollen' => 'yes'], 'SPECIAL_HANDLING', 'Ada indikasi kondisi baterai yang memerlukan penanganan khusus.'],
            ['Penanganan khusus untuk baterai bocor', 3, ['battery_leaking' => 'yes'], 'SPECIAL_HANDLING', 'Ada indikasi kebocoran atau korosi baterai yang memerlukan penanganan khusus.'],
            ['Penanganan khusus untuk sistem pendingin bocor', 4, ['cooling_leak' => 'yes'], 'SPECIAL_HANDLING', 'Ada indikasi kerusakan atau kebocoran pada sistem pendingin yang memerlukan penanganan khusus.'],
            ['Donasi perangkat normal sesuai prioritas warga', 15, ['power_status' => 'normal', 'damage_level' => 'none', 'user_intent' => 'donate'], 'DONATION', 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.'],
            ['Donasi perangkat dengan kerusakan ringan sesuai prioritas warga', 16, ['power_status' => 'normal', 'damage_level' => 'minor', 'user_intent' => 'donate'], 'DONATION', 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.'],
            ['Guna ulang perangkat yang masih normal', 20, ['power_status' => 'normal', 'damage_level' => 'none'], 'REUSE', 'Perangkat masih berfungsi; penggunaan kembali diprioritaskan.'],
            ['Pemeriksaan perbaikan untuk fungsi bermasalah', 30, ['power_status' => 'partial'], 'REPAIR_ASSESSMENT', 'Perangkat masih menunjukkan fungsi; pemeriksaan perbaikan disarankan.'],
            ['Pemulihan komponen setelah dinyatakan tidak layak diperbaiki', 40, ['power_status' => 'off', 'technician_result' => 'not_repairable'], 'PARTS_RECOVERY', 'Perangkat telah dinyatakan tidak layak diperbaiki.'],
        ];
        foreach ($rules as $rule) {
            CircularRule::updateOrCreate(
                ['name' => $rule[0]],
                ['priority' => $rule[1], 'active' => true, 'conditions_json' => $rule[2], 'result_path' => $rule[3], 'explanation_template' => $rule[4]]
            );
        }

        foreach ((array) config('surabaya_regions', []) as $district => $villages) {
            Region::updateOrCreate(
                ['level' => 'district', 'city' => 'Surabaya', 'name' => $district],
                ['province' => 'Jawa Timur', 'district' => $district, 'active' => true]
            );
            foreach ($villages as $village) {
                Region::updateOrCreate(
                    ['level' => 'village', 'city' => 'Surabaya', 'district' => $district, 'name' => $village],
                    ['province' => 'Jawa Timur', 'village' => $village, 'active' => true]
                );
            }
        }

        $settings = [
            ['ai.enabled', '1', 'boolean', 'ai'],
            ['ai.monthly_budget_usd', '20', 'float', 'ai'],
            ['ai.default_model', config('sirkel.ai.default_model'), 'string', 'ai'],
            ['ai.escalation_model', config('sirkel.ai.escalation_model'), 'string', 'ai'],
            ['ai.escalation_confidence', (string) config('sirkel.ai.escalation_confidence'), 'float', 'ai'],
            ['ai.image_detail', config('sirkel.ai.image_detail'), 'string', 'ai'],
            ['ai.quota.asset_intake_free', (string) config('sirkel.ai.quota.asset_intake_free', 5), 'integer', 'ai'],
            ['ai.quota.condition_description_free', (string) config('sirkel.ai.quota.condition_description_free', 20), 'integer', 'ai'],
            ['ai.quota.asset_intake_price_idr', (string) config('sirkel.ai.quota.asset_intake_price_idr', 2000), 'integer', 'ai'],
            ['ai.quota.condition_description_price_idr', (string) config('sirkel.ai.quota.condition_description_price_idr', 500), 'integer', 'ai'],
            ['ai.topup_admin_whatsapp', '6289650484363', 'string', 'ai'],
        ];
        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting[0]],
                ['value' => $setting[1], 'type' => $setting[2], 'group' => $setting[3]]
            );
        }
    }

    private function question(QuestionnaireTemplate $template, string $code, string $text, string $type, int $order, array $options = [], ?string $help = null): void
    {
        $question = Question::updateOrCreate(
            ['questionnaire_template_id' => $template->id, 'code' => $code],
            ['text' => $text, 'type' => $type, 'required' => $type !== 'text', 'sort_order' => $order, 'help_text' => $help]
        );
        $question->options()->delete();
        foreach ($options as $i => $option) {
            QuestionOption::create([
                'question_id' => $question->id,
                'value' => $option[0],
                'label' => $option[1],
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function cloneQuestions(QuestionnaireTemplate $from, QuestionnaireTemplate $to): void
    {
        foreach ($from->questions()->with('options')->get() as $question) {
            $this->question(
                $to,
                $question->code,
                $question->text,
                $question->type,
                $question->sort_order,
                $question->options->map(fn ($option) => [$option->value, $option->label])->all(),
                $question->help_text
            );
        }
    }
}
