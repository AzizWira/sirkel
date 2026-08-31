<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('partner_profiles', 'admin_status')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                $table->string('admin_status', 20)->default('inactive')->index()->after('verification_status');
            });
        }

        DB::table('partner_profiles')
            ->where('verification_status', 'approved')
            ->update(['admin_status' => 'active']);

        DB::table('partner_profiles')
            ->where('verification_status', '!=', 'approved')
            ->update(['admin_status' => 'inactive', 'accepting_requests' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('partner_profiles', 'admin_status')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                $table->dropColumn('admin_status');
            });
        }
    }
};
