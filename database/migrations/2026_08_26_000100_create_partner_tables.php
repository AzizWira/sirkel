<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('business_name');
            $t->string('responsible_name');
            $t->string('phone', 32);
            $t->text('address');
            $t->string('district')->nullable()->index();
            $t->string('village')->nullable();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('pickup_radius_km', 6, 2)->default(10);
            $t->boolean('accepting_requests')->default(true);
            $t->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('identity_file_path')->nullable();
            $t->timestamp('identity_delete_after')->nullable()->index();
            $t->timestamp('identity_deleted_at')->nullable();
            $t->string('place_photo_path')->nullable();
            $t->json('operating_hours_json')->nullable();
            $t->timestamps(); });
        Schema::create('partner_capabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $t->string('capability')->index();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['partner_profile_id', 'capability']); }); }
    public function down(): void
    {
        Schema::dropIfExists('partner_capabilities');
        Schema::dropIfExists('partner_profiles'); }
};
