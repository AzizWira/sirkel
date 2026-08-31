<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $groups = [
            'mobile-computing' => ['Ponsel & Komputasi', 'Perangkat komputasi personal dan perangkat mobile'],
            'accessories-power' => ['Aksesori & Daya', 'Aksesori elektronik, sumber daya, baterai, dan kabel'],
            'small-household' => ['Elektronik Rumah Tangga Kecil', 'Peralatan elektronik rumah tangga berukuran kecil'],
            'office-peripheral' => ['Perangkat Kantor & Periferal', 'Periferal dan perangkat kantor berukuran kecil'],
            'other-small' => ['Elektronik Kecil Lainnya', 'Perangkat elektronik kecil yang belum tersedia pada kategori utama'],
        ];

        foreach ($groups as $code => [$name, $description]) {
            DB::table('device_groups')->where('code', $code)->update([
                'name' => $name,
                'description' => $description,
            ]);
        }


        $categoryNames = [
            'electric-kettle' => 'Ketel Listrik',
            'toaster' => 'Pemanggang Roti',
            'hair-dryer' => 'Pengering Rambut',
        ];
        foreach ($categoryNames as $code => $name) {
            DB::table('device_categories')->where('code', $code)->update(['name' => $name]);
        }

        $rules = [
            'Special handling safety' => 'Penanganan khusus untuk baterai berisiko',
            'Reuse working device' => 'Guna ulang perangkat yang masih normal',
            'Repair partial function' => 'Pemeriksaan perbaikan untuk fungsi bermasalah',
            'Parts recovery after technician' => 'Pemulihan komponen setelah dinyatakan tidak layak diperbaiki',
        ];

        foreach ($rules as $old => $new) {
            DB::table('circular_rules')->where('name', $old)->update(['name' => $new]);
        }


        // Versi sebelumnya dapat menyimpan tahap antara sebagai seolah-olah hasil akhir.
        // Pulihkan record tersebut agar alur penanganan dapat dilanjutkan di workspace mitra.
        DB::table('assets')
            ->where('final_path', 'RECEIVED_BY_RECOVERY_PARTNER')
            ->update(['final_path' => null, 'status' => 'received']);

        $unverifiedAssetIds = DB::table('assets')
            ->where('final_path', 'UNVERIFIED_FINAL_TREATMENT')
            ->pluck('id');

        foreach ($unverifiedAssetIds as $assetId) {
            $latestCustody = DB::table('asset_custodies')
                ->where('asset_id', $assetId)
                ->orderByDesc('id')
                ->first();

            if (!$latestCustody) {
                continue;
            }

            DB::table('asset_custodies')->where('id', $latestCustody->id)->update(['released_at' => null]);
            DB::table('assets')->where('id', $assetId)->update([
                'final_path' => null,
                'status' => 'needs_transfer',
            ]);

            DB::table('asset_events')->insert([
                'asset_id' => $assetId,
                'actor_user_id' => null,
                'event_type' => 'FLOW_CORRECTED',
                'title' => 'Status penanganan diperbaiki',
                'description' => 'Penutupan tanpa hasil terverifikasi dikembalikan menjadi proses lanjutan agar mitra dapat menentukan hasil yang benar atau mengalihkan barang.',
                'metadata_json' => json_encode(['version' => '1.0.14']),
                'occurred_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Perubahan ini hanya memperbaiki istilah tampilan, tidak perlu dikembalikan.
    }
};
