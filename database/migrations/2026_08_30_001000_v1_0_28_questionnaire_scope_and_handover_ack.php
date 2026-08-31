<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questionnaire_templates', function (Blueprint $table) {
            $table->foreignId('device_group_id')->nullable()->after('device_category_id')->constrained('device_groups')->nullOnDelete();
            $table->string('audience', 20)->default('citizen')->after('device_group_id')->index();
            $table->index(['audience', 'device_category_id']);
            $table->index(['audience', 'device_group_id']);
        });

        DB::table('questionnaire_templates')->update(['audience' => 'citizen']);

        Schema::table('handover_requests', function (Blueprint $table) {
            $table->timestamp('ownership_acknowledged_at')->nullable()->after('handover_type');
        });
    }

    public function down(): void
    {
        Schema::table('handover_requests', function (Blueprint $table) {
            $table->dropColumn('ownership_acknowledged_at');
        });

        Schema::table('questionnaire_templates', function (Blueprint $table) {
            $table->dropForeign(['device_group_id']);
            $table->dropIndex(['audience', 'device_category_id']);
            $table->dropIndex(['audience', 'device_group_id']);
            $table->dropIndex(['audience']);
            $table->dropColumn(['device_group_id', 'audience']);
        });
    }
};
