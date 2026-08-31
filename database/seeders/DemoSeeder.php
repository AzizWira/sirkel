<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\{
    Asset,
    AssetAssessment,
    AssetEvent,
    DeviceCategory,
    HandoverRequest,
    Offer,
    PartnerCapabilityModel,
    PartnerProfile,
    User
};
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    private const DEMO_CONTACT_NUMBER = '6289650484363';

    public function run(): void
    {
        $password = 'password123';

        $admin = User::updateOrCreate(
            ['email' => 'admin@sirkel.awicode.com'],
            [
                'name' => 'Admin SIRKEL',
                'password' => $password,
                'role' => UserRole::ADMIN,
                'whatsapp' => self::DEMO_CONTACT_NUMBER,
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
                'district' => 'Genteng',
                'village' => 'Embong Kaliasin',
            ]
        );

        $warga = User::updateOrCreate(
            ['email' => 'warga@sirkel.awicode.com'],
            [
                'name' => 'Warga Demo',
                'password' => $password,
                'role' => UserRole::USER,
                'whatsapp' => self::DEMO_CONTACT_NUMBER,
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
                'district' => 'Gunung Anyar',
                'village' => 'Gunung Anyar Tambak',
            ]
        );

        $allCategoryCodes = DeviceCategory::query()->where('active', true)->pluck('code')->all();
        $codesForGroups = static fn (array $groups): array => DeviceCategory::query()
            ->where('active', true)
            ->whereHas('group', fn ($q) => $q->whereIn('code', $groups))
            ->pluck('code')
            ->all();

        $mobile = $codesForGroups(['mobile-computing']);
        $power = $codesForGroups(['accessories-power']);
        $smallHousehold = $codesForGroups(['small-household']);
        $largeHousehold = $codesForGroups(['large-household']);
        $office = $codesForGroups(['office-peripheral']);
        $audioVideo = $codesForGroups(['audio-video']);
        $gaming = $codesForGroups(['gaming-entertainment']);
        $personalCare = $codesForGroups(['personal-care']);
        $lightingTools = $codesForGroups(['lighting-tools']);
        $unknown = ['uncategorized-electronics'];
        $batteryRisk = ['battery', 'powerbank', 'ups', 'smartphone', 'feature-phone', 'tablet', 'laptop', 'smartwatch', 'e-reader', 'headphones', 'earphones', 'handheld-console', 'game-controller', 'emergency-lamp', 'electric-shaver', 'hair-clipper', 'electric-toothbrush', 'digital-scale', 'digital-thermometer', 'blood-pressure-monitor', 'electric-screwdriver'];

        // Demo partners deliberately overlap categories and capabilities. Generic “Lainnya”
        // categories are included through their groups, so a new/unlisted device does not
        // immediately dead-end merely because its exact product name is absent from the catalogue.
        $specs = [
            [
                'email' => 'repair@sirkel.awicode.com',
                'name' => 'Sirkular Service Gunung Anyar',
                'district' => 'Gunung Anyar',
                'village' => 'Gunung Anyar',
                'lat' => -7.3375,
                'lng' => 112.7850,
                'radius' => 8,
                'caps' => ['collection', 'pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, ['charger', 'cable', 'power-adapter']))),
            ],
            [
                'email' => 'repair-rungkut@sirkel.awicode.com',
                'name' => 'Tekno Repair Rungkut',
                'district' => 'Rungkut',
                'village' => 'Kalirungkut',
                'lat' => -7.3218,
                'lng' => 112.7715,
                'radius' => 10,
                'caps' => ['collection', 'pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $power, $smallHousehold, $office, $audioVideo, $gaming, $unknown))),
            ],
            [
                'email' => 'repair-sukolilo@sirkel.awicode.com',
                'name' => 'Elektronik Sehat Sukolilo',
                'district' => 'Sukolilo',
                'village' => 'Keputih',
                'lat' => -7.2825,
                'lng' => 112.7940,
                'radius' => 9,
                'caps' => ['pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($smallHousehold, $largeHousehold, $personalCare, $lightingTools, $unknown))),
            ],
            [
                'email' => 'devicecare@sirkel.awicode.com',
                'name' => 'Surabaya Device Care',
                'district' => 'Wonokromo',
                'village' => 'Darmo',
                'lat' => -7.3025,
                'lng' => 112.7345,
                'radius' => 7,
                'caps' => ['collection', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $office, $audioVideo, $gaming))),
            ],
            [
                'email' => 'homecare@sirkel.awicode.com',
                'name' => 'Surabaya Home Appliance Care',
                'district' => 'Wonokromo',
                'village' => 'Ngagel',
                'lat' => -7.3060,
                'lng' => 112.7440,
                'radius' => 12,
                'caps' => ['collection', 'pickup', 'repair', 'recovery'],
                'categories' => array_values(array_unique(array_merge($power, $smallHousehold, $largeHousehold, $unknown))),
            ],
            [
                'email' => 'avrepair@sirkel.awicode.com',
                'name' => 'Audio Visual Service Gubeng',
                'district' => 'Gubeng',
                'village' => 'Mojo',
                'lat' => -7.2768,
                'lng' => 112.7615,
                'radius' => 10,
                'caps' => ['collection', 'pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($audioVideo, $gaming, ['projector', 'webcam']))),
            ],
            [
                'email' => 'coolingcare@sirkel.awicode.com',
                'name' => 'Surabaya Cooling Service',
                'district' => 'Tenggilis Mejoyo',
                'village' => 'Tenggilis Mejoyo',
                'lat' => -7.3155,
                'lng' => 112.7575,
                'radius' => 13,
                'caps' => ['collection', 'pickup', 'repair', 'recovery', 'special_handling'],
                'categories' => array_values(array_unique(array_merge($largeHousehold, ['voltage-stabilizer'], $unknown))),
            ],
            [
                'email' => 'donation@sirkel.awicode.com',
                'name' => 'Reuse Hub Surabaya',
                'district' => 'Gubeng',
                'village' => 'Airlangga',
                'lat' => -7.3030,
                'lng' => 112.7630,
                'radius' => 12,
                'caps' => ['collection', 'pickup', 'reuse_donation'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $largeHousehold, $office, $audioVideo, $gaming, $personalCare))),
            ],
            [
                'email' => 'donation-rungkut@sirkel.awicode.com',
                'name' => 'Berbagi Elektronik Rungkut',
                'district' => 'Rungkut',
                'village' => 'Kedung Baruk',
                'lat' => -7.3120,
                'lng' => 112.7820,
                'radius' => 10,
                'caps' => ['collection', 'pickup', 'reuse_donation'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $office, $audioVideo, $gaming))),
            ],
            [
                'email' => 'secondlife@sirkel.awicode.com',
                'name' => 'Second Life Surabaya',
                'district' => 'Tegalsari',
                'village' => 'Kedungdoro',
                'lat' => -7.2745,
                'lng' => 112.7350,
                'radius' => 6,
                'caps' => ['collection', 'reuse_donation'],
                'categories' => array_values(array_unique(array_merge($mobile, $office, $audioVideo, $gaming))),
            ],
            [
                'email' => 'recovery@sirkel.awicode.com',
                'name' => 'Mitra Recovery Surabaya',
                'district' => 'Wonokromo',
                'village' => 'Sawunggaling',
                'lat' => -7.2955,
                'lng' => 112.7380,
                'radius' => 20,
                'caps' => ['collection', 'pickup', 'recovery', 'special_handling'],
                'categories' => $allCategoryCodes,
            ],
            [
                'email' => 'recovery-rungkut@sirkel.awicode.com',
                'name' => 'Eco Material Rungkut',
                'district' => 'Rungkut',
                'village' => 'Penjaringansari',
                'lat' => -7.3235,
                'lng' => 112.7865,
                'radius' => 14,
                'caps' => ['collection', 'pickup', 'recovery', 'special_handling'],
                'categories' => $allCategoryCodes,
            ],
            [
                'email' => 'recovery-wonocolo@sirkel.awicode.com',
                'name' => 'Recovery Hub Margorejo',
                'district' => 'Wonocolo',
                'village' => 'Margorejo',
                'lat' => -7.3320,
                'lng' => 112.7395,
                'radius' => 8,
                'caps' => ['collection', 'recovery'],
                'categories' => array_values(array_unique(array_merge($mobile, $power, $smallHousehold, $largeHousehold, $office, $audioVideo, $gaming, $personalCare, $lightingTools))),
            ],
            [
                'email' => 'battery@sirkel.awicode.com',
                'name' => 'Baterai Aman Surabaya',
                'district' => 'Sukolilo',
                'village' => 'Gebang Putih',
                'lat' => -7.2770,
                'lng' => 112.7900,
                'radius' => 13,
                'caps' => ['collection', 'pickup', 'special_handling'],
                'categories' => array_values(array_unique(array_merge($batteryRisk, ['other-accessories-power', 'uncategorized-electronics']))),
            ],
            [
                'email' => 'repair-tenggilis@sirkel.awicode.com',
                'name' => 'Elektronik Prima Tenggilis',
                'district' => 'Tenggilis Mejoyo',
                'village' => 'Kendangsari',
                'lat' => -7.3196,
                'lng' => 112.7542,
                'radius' => 9,
                'caps' => ['collection', 'pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $office, $power, $unknown))),
            ],
            [
                'email' => 'repair-mulyorejo@sirkel.awicode.com',
                'name' => 'Mulyorejo Tech Repair',
                'district' => 'Mulyorejo',
                'village' => 'Kalisari',
                'lat' => -7.2728,
                'lng' => 112.8015,
                'radius' => 10,
                'caps' => ['pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $office, $audioVideo, $gaming, $power))),
            ],
            [
                'email' => 'repair-tandes@sirkel.awicode.com',
                'name' => 'Tandes Elektronik Care',
                'district' => 'Tandes',
                'village' => 'Manukan Wetan',
                'lat' => -7.2515,
                'lng' => 112.6762,
                'radius' => 11,
                'caps' => ['collection', 'repair'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $office, $audioVideo, $power, $unknown))),
            ],
            [
                'email' => 'homecare-wiyung@sirkel.awicode.com',
                'name' => 'Wiyung Appliance Service',
                'district' => 'Wiyung',
                'village' => 'Babatan',
                'lat' => -7.3117,
                'lng' => 112.6925,
                'radius' => 12,
                'caps' => ['collection', 'pickup', 'repair'],
                'categories' => array_values(array_unique(array_merge($power, $smallHousehold, $largeHousehold, $personalCare))),
            ],
            [
                'email' => 'donation-gununganyar@sirkel.awicode.com',
                'name' => 'Gunung Anyar Reuse Point',
                'district' => 'Gunung Anyar',
                'village' => 'Rungkut Tengah',
                'lat' => -7.3304,
                'lng' => 112.7796,
                'radius' => 8,
                'caps' => ['collection', 'pickup', 'reuse_donation'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $office, $audioVideo, $gaming))),
            ],
            [
                'email' => 'donation-gayungan@sirkel.awicode.com',
                'name' => 'Rumah Guna Ulang Gayungan',
                'district' => 'Gayungan',
                'village' => 'Ketintang',
                'lat' => -7.3180,
                'lng' => 112.7262,
                'radius' => 10,
                'caps' => ['collection', 'reuse_donation'],
                'categories' => array_values(array_unique(array_merge($mobile, $smallHousehold, $largeHousehold, $office, $audioVideo, $gaming, $personalCare))),
            ],
            [
                'email' => 'recovery-kenjeran@sirkel.awicode.com',
                'name' => 'Recovery Material Kenjeran',
                'district' => 'Kenjeran',
                'village' => 'Tanah Kali Kedinding',
                'lat' => -7.2276,
                'lng' => 112.7804,
                'radius' => 16,
                'caps' => ['collection', 'pickup', 'recovery', 'special_handling'],
                'categories' => $allCategoryCodes,
            ],
            [
                'email' => 'recovery-tandes@sirkel.awicode.com',
                'name' => 'Sirkular Material Tandes',
                'district' => 'Tandes',
                'village' => 'Balongsari',
                'lat' => -7.2568,
                'lng' => 112.6814,
                'radius' => 14,
                'caps' => ['collection', 'recovery'],
                'categories' => array_values(array_unique(array_merge($mobile, $power, $smallHousehold, $largeHousehold, $office, $audioVideo, $lightingTools, $unknown))),
            ],
            [
                'email' => 'battery-gubeng@sirkel.awicode.com',
                'name' => 'Safe Battery Gubeng',
                'district' => 'Gubeng',
                'village' => 'Mojo',
                'lat' => -7.2762,
                'lng' => 112.7618,
                'radius' => 10,
                'caps' => ['collection', 'pickup', 'special_handling'],
                'categories' => array_values(array_unique(array_merge($batteryRisk, ['other-accessories-power', 'uncategorized-electronics']))),
            ],
            [
                'email' => 'collection-lakarsantri@sirkel.awicode.com',
                'name' => 'Pos Elektronik Lakarsantri',
                'district' => 'Lakarsantri',
                'village' => 'Lidah Wetan',
                'lat' => -7.3042,
                'lng' => 112.6588,
                'radius' => 13,
                'caps' => ['collection', 'pickup'],
                'categories' => $allCategoryCodes,
            ],
            [
                'email' => 'collection@sirkel.awicode.com',
                'name' => 'Pusat Koleksi Rungkut',
                'district' => 'Rungkut',
                'village' => 'Kalirungkut',
                'lat' => -7.3180,
                'lng' => 112.7750,
                'radius' => 10,
                'caps' => ['collection', 'pickup'],
                'categories' => $allCategoryCodes,
            ],
        ];

        $partners = [];
        foreach ($specs as $index => $spec) {
            $user = User::updateOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => $password,
                    'role' => UserRole::PARTNER,
                    'whatsapp' => self::DEMO_CONTACT_NUMBER,
                    'email_verified_at' => now(),
                    'profile_completed_at' => now(),
                    'district' => $spec['district'],
                    'village' => $spec['village'],
                ]
            );

            $profile = PartnerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $spec['name'],
                    'responsible_name' => 'Operator Demo '.($index + 1),
                    'phone' => $user->whatsapp,
                    'address' => 'Lokasi demo '.$spec['name'].', Surabaya',
                    'district' => $spec['district'],
                    'village' => $spec['village'],
                    'latitude' => $spec['lat'],
                    'longitude' => $spec['lng'],
                    'pickup_radius_km' => $spec['radius'],
                    'accepting_requests' => true,
                    'verification_status' => 'approved',
                    'admin_status' => 'active',
                    'verified_at' => now(),
                    'partner_access_granted_at' => now(),
                    'approval_acknowledged_at' => now(),
                    'verified_by' => $admin->id,
                    'operating_hours_json' => ['mon-fri' => '08:00-17:00', 'sat' => '09:00-15:00'],
                ]
            );

            $categoryIds = DeviceCategory::query()
                ->whereIn('code', $spec['categories'])
                ->pluck('id');
            $profile->acceptedCategories()->sync($categoryIds);

            PartnerCapabilityModel::where('partner_profile_id', $profile->id)
                ->whereNotIn('capability', $spec['caps'])
                ->delete();
            foreach ($spec['caps'] as $capability) {
                PartnerCapabilityModel::updateOrCreate(
                    ['partner_profile_id' => $profile->id, 'capability' => $capability],
                    ['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]
                );
            }

            $partners[$spec['email']] = $profile;
        }

        $smartphone = DeviceCategory::where('code', 'smartphone')->firstOrFail();
        $blender = DeviceCategory::where('code', 'blender')->firstOrFail();
        $battery = DeviceCategory::where('code', 'battery')->firstOrFail();
        $repairPartner = $partners['repair@sirkel.awicode.com'];

        $asset1 = Asset::updateOrCreate(
            ['passport_code' => 'SRK-I-DEMO01'],
            [
                'owner_user_id' => $warga->id,
                'device_category_id' => $smartphone->id,
                'tracking_type' => 'individual',
                'brand' => 'Samsung',
                'model_name' => 'Galaxy A52',
                'description' => 'Layar retak, perangkat masih menyala',
                'quantity' => 1,
                'preliminary_path' => 'REPAIR_ASSESSMENT',
                'final_path' => 'REPAIRED',
                'status' => 'repaired',
                'verified_weight_kg' => 0.189,
                'origin_district' => 'Gunung Anyar',
                'origin_village' => 'Gunung Anyar Tambak',
                'core_locked_at' => now()->subDays(2),
            ]
        );

        AssetAssessment::updateOrCreate(
            ['asset_id' => $asset1->id, 'assessment_type' => 'partner'],
            [
                'assessor_user_id' => $repairPartner->user_id,
                'answers_json' => ['power_status' => 'normal', 'damage_level' => 'minor'],
                'result_path' => 'REPAIRED',
                'summary' => 'Kerusakan display ditangani dan perangkat berhasil dipulihkan untuk masuk kembali ke jalur penggunaan.',
                'verified_weight_kg' => 0.189,
                'verified_at' => now()->subDay(),
            ]
        );

        foreach ([
            ['REGISTERED', 'Barang didaftarkan', 'Warga mendaftarkan smartphone.', -4],
            ['PRELIMINARY_ASSESSMENT', 'Cek kondisi selesai', 'Rekomendasi awal repair assessment.', -4],
            ['RECEIVED', 'Barang diterima mitra', 'Berat terverifikasi 0,189 kg.', -2],
            ['VERIFIED_OUTCOME', 'Outcome terverifikasi', 'Life extended through repair.', -1],
        ] as $event) {
            AssetEvent::updateOrCreate(
                ['asset_id' => $asset1->id, 'event_type' => $event[0]],
                [
                    'actor_user_id' => $warga->id,
                    'title' => $event[1],
                    'description' => $event[2],
                    'occurred_at' => now()->addDays($event[3]),
                ]
            );
        }

        $asset2 = Asset::updateOrCreate(
            ['passport_code' => 'SRK-I-DEMO02'],
            [
                'owner_user_id' => $warga->id,
                'device_category_id' => $blender->id,
                'tracking_type' => 'individual',
                'brand' => 'Miyako',
                'description' => 'Motor berbunyi tetapi pisau tidak berputar',
                'quantity' => 1,
                'preliminary_path' => 'REPAIR_ASSESSMENT',
                'status' => 'offered',
                'origin_district' => 'Gunung Anyar',
                'origin_village' => 'Rungkut Menanggal',
            ]
        );

        $request = HandoverRequest::updateOrCreate(
            ['asset_id' => $asset2->id, 'status' => 'offered'],
            [
                'user_id' => $warga->id,
                'partner_profile_id' => $repairPartner->id,
                'method' => 'pickup',
                'pickup_latitude' => -7.334,
                'pickup_longitude' => 112.786,
                'pickup_address' => 'Alamat pickup demo (hanya mitra terpilih)',
                'pickup_district' => 'Gunung Anyar',
                'pickup_village' => 'Rungkut Menanggal',
                'distance_km' => 1.2,
                'within_radius' => true,
                'outside_radius' => false,
                'requested_date' => now()->addDay()->toDateString(),
                'requested_time_start' => '13:00',
                'requested_time_end' => '16:00',
                'accepted_at' => now(),
            ]
        );

        Offer::updateOrCreate(
            ['handover_request_id' => $request->id, 'version' => 1],
            [
                'amount' => 35000,
                'note' => 'Estimasi berdasarkan foto; dapat berubah setelah pemeriksaan fisik.',
                'offered_at' => now(),
                'expires_at' => now()->addHours(12),
                'is_current' => true,
                'status' => 'waiting_user',
            ]
        );

        Asset::updateOrCreate(
            ['passport_code' => 'SRK-B-DEMO03'],
            [
                'owner_user_id' => $warga->id,
                'device_category_id' => $battery->id,
                'tracking_type' => 'batch',
                'custom_item_name' => 'Baterai AA bekas',
                'description' => 'Batch homogen untuk demo special handling',
                'quantity' => 12,
                'condition_class' => 'used-normal',
                'preliminary_path' => 'SPECIAL_HANDLING',
                'status' => 'matching',
                'origin_district' => 'Gunung Anyar',
                'origin_village' => 'Gunung Anyar',
            ]
        );
    }
}
