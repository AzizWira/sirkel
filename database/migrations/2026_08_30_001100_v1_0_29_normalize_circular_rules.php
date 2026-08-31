<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('circular_rules')) {
            return;
        }

        DB::table('circular_rules')
            ->where('name', 'Donasi untuk perangkat layak sesuai prioritas warga')
            ->delete();

        $rows = [
            [
                'name' => 'Donasi perangkat normal sesuai prioritas warga',
                'priority' => 15,
                'active' => true,
                'conditions_json' => json_encode([
                    'power_status' => 'normal',
                    'damage_level' => 'none',
                    'user_intent' => 'donate',
                ], JSON_UNESCAPED_UNICODE),
                'result_path' => 'DONATION',
                'explanation_template' => 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.',
            ],
            [
                'name' => 'Donasi perangkat dengan kerusakan ringan sesuai prioritas warga',
                'priority' => 16,
                'active' => true,
                'conditions_json' => json_encode([
                    'power_status' => 'normal',
                    'damage_level' => 'minor',
                    'user_intent' => 'donate',
                ], JSON_UNESCAPED_UNICODE),
                'result_path' => 'DONATION',
                'explanation_template' => 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.',
            ],
        ];

        foreach ($rows as $row) {
            $existing = DB::table('circular_rules')->where('name', $row['name'])->first();
            if ($existing) {
                DB::table('circular_rules')->where('id', $existing->id)->update($row + ['updated_at' => now()]);
            } else {
                DB::table('circular_rules')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('circular_rules')) {
            return;
        }

        DB::table('circular_rules')
            ->whereIn('name', [
                'Donasi perangkat normal sesuai prioritas warga',
                'Donasi perangkat dengan kerusakan ringan sesuai prioritas warga',
            ])
            ->delete();

        DB::table('circular_rules')->insert([
            'name' => 'Donasi untuk perangkat layak sesuai prioritas warga',
            'priority' => 15,
            'active' => true,
            'conditions_json' => json_encode([
                'power_status' => 'normal',
                'damage_level' => ['none', 'minor'],
                'user_intent' => 'donate',
            ], JSON_UNESCAPED_UNICODE),
            'result_path' => 'DONATION',
            'explanation_template' => 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
