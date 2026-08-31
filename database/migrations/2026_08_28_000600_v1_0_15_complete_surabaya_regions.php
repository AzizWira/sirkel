<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('regions')) {
            return;
        }

        $master = require config_path('surabaya_regions.php');
        $now = now();

        DB::transaction(function () use ($master, $now) {
            foreach ($master as $district => $villages) {
                DB::table('regions')->updateOrInsert(
                    ['level' => 'district', 'city' => 'Surabaya', 'name' => $district],
                    [
                        'province' => 'Jawa Timur',
                        'district' => $district,
                        'village' => null,
                        'active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                foreach ($villages as $village) {
                    DB::table('regions')->updateOrInsert(
                        [
                            'level' => 'village',
                            'city' => 'Surabaya',
                            'district' => $district,
                            'name' => $village,
                        ],
                        [
                            'province' => 'Jawa Timur',
                            'village' => $village,
                            'active' => true,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            // Nonaktifkan nomenklatur lama yang sudah digantikan/dirapikan.
            DB::table('regions')
                ->where('city', 'Surabaya')
                ->where('level', 'district')
                ->where('name', 'Asemrowo')
                ->update(['active' => false, 'updated_at' => $now]);

            DB::table('regions')
                ->where('city', 'Surabaya')
                ->where('level', 'village')
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('district', 'Rungkut')->whereIn('name', ['Kali Rungkut', 'Penjaringan Sari']);
                    })->orWhere(function ($q) {
                        $q->where('district', 'Mulyorejo')->where('name', 'Kejawaan Putih Tambak');
                    })->orWhere(function ($q) {
                        $q->where('district', 'Pabean Cantian')->whereIn('name', ['Perak Utara', 'Perak Timur']);
                    });
                })
                ->update(['active' => false, 'updated_at' => $now]);
        });

        // Rapikan district lama pada data profil/operasional. Riwayat kelurahan
        // lama tidak dipaksa berubah; RegionService masih mengenali aliasnya.
        foreach ([
            ['users', 'district'],
            ['partner_profiles', 'district'],
            ['assets', 'origin_district'],
            ['handover_requests', 'pickup_district'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, 'Asemrowo')->update([$column => 'Asem Rowo']);
            }
        }

        // Profil aktif memakai nomenklatur terkini agar dropdown dapat memilih
        // ulang nilai yang tersimpan tanpa menampilkan opsi kosong.
        foreach (['users', 'partner_profiles'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'district') || !Schema::hasColumn($table, 'village')) {
                continue;
            }

            DB::table($table)->where('district', 'Rungkut')->where('village', 'Kali Rungkut')->update(['village' => 'Kalirungkut']);
            DB::table($table)->where('district', 'Rungkut')->where('village', 'Penjaringan Sari')->update(['village' => 'Penjaringansari']);
            DB::table($table)->where('district', 'Mulyorejo')->where('village', 'Kejawaan Putih Tambak')->update(['village' => 'Kejawan Putih Tambak']);
            DB::table($table)->where('district', 'Pabean Cantian')->whereIn('village', ['Perak Utara', 'Perak Timur'])->update(['village' => 'Tanjung Perak']);
        }
    }

    public function down(): void
    {
        // Data master wilayah tidak dihapus pada rollback agar profil dan
        // riwayat yang sudah mereferensikannya tetap aman.
    }
};
