<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    private array $tables = [
        'assets',
        'handover_requests',
        'offers',
        'partner_transfers',
        'partner_profiles',
        'issue_reports',
        'device_groups',
        'device_categories',
        'questionnaire_templates',
        'circular_rules',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'public_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('public_id', 26)->nullable()->unique()->after('id');
                });
            }

            DB::table($tableName)
                ->whereNull('public_id')
                ->orderBy('id')
                ->select('id')
                ->chunkById(200, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['public_id' => (string) Str::ulid()]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (Schema::hasColumn($tableName, 'public_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropUnique($tableName . '_public_id_unique');
                    $table->dropColumn('public_id');
                });
            }
        }
    }
};
