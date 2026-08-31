<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_profiles', function (Blueprint $table) {
            $table->timestamp('partner_access_granted_at')->nullable()->after('verified_at');
            $table->timestamp('approval_acknowledged_at')->nullable()->after('partner_access_granted_at');
        });

        // Approved partner lama langsung mendapat entitlement Mitra dan dianggap
        // sudah mengenal mode Mitra. Pengajuan baru setelah upgrade akan mendapat
        // entitlement saat admin menyetujui, sedangkan acknowledgement menunggu
        // warga menekan tombol "Paham" pada halaman status Jadi Mitra.
        DB::table('partner_profiles')
            ->where('verification_status', 'approved')
            ->update([
                'partner_access_granted_at' => DB::raw('COALESCE(verified_at, CURRENT_TIMESTAMP)'),
                'approval_acknowledged_at' => DB::raw('COALESCE(verified_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('partner_profiles', function (Blueprint $table) {
            $table->dropColumn(['partner_access_granted_at', 'approval_acknowledged_at']);
        });
    }
};
