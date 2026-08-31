<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('intake_sessions', function (Blueprint $table) {
            $table->json('handover_context_json')->nullable()->after('bulk_context_json');
        });

        Schema::create('donation_proofs', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('asset_id')->unique()->constrained('assets')->cascadeOnDelete();
            $table->foreignId('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_type', 40);
            $table->string('recipient_name', 160)->nullable();
            $table->text('recipient_note')->nullable();
            $table->string('photo_path');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('location_accuracy_m', 10, 2)->nullable();
            $table->string('location_label', 180)->nullable();
            $table->dateTime('donated_at');
            $table->string('status', 24)->default('recorded')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_proofs');
        Schema::table('intake_sessions', function (Blueprint $table) {
            $table->dropColumn('handover_context_json');
        });
    }
};
