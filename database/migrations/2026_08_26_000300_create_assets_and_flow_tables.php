<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('passport_code')->unique();
            $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('device_category_id')->constrained()->restrictOnDelete();
            $t->foreignId('parent_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $t->enum('tracking_type', ['individual', 'batch'])->default('individual');
            $t->string('custom_item_name')->nullable();
            $t->string('brand')->nullable();
            $t->string('model_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->string('condition_class')->nullable();
            $t->decimal('estimated_weight_kg', 10, 3)->nullable();
            $t->decimal('verified_weight_kg', 10, 3)->nullable();
            $t->date('dormant_since')->nullable();
            $t->string('preliminary_path')->nullable();
            $t->string('final_path')->nullable();
            $t->string('status')->default('registered')->index();
            $t->enum('handover_type', ['sale', 'free_handover', 'donation'])->nullable();
            $t->timestamp('core_locked_at')->nullable();
            $t->string('origin_district')->nullable()->index();
            $t->string('origin_village')->nullable();
            $t->softDeletes();
            $t->timestamps(); });
        Schema::create('asset_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->boolean('is_primary')->default(false);
            $t->unsignedTinyInteger('sort_order')->default(0);
            $t->timestamps(); });
        Schema::create('asset_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->enum('assessment_type', ['user', 'partner']);
            $t->foreignId('assessor_user_id')->constrained('users')->cascadeOnDelete();
            $t->json('answers_json');
            $t->string('result_path')->nullable();
            $t->text('summary')->nullable();
            $t->decimal('verified_weight_kg', 10, 3)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps(); });
        Schema::create('handover_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $t->enum('method', ['pickup', 'dropoff']);
            $t->string('status')->default('pending')->index();
            $t->decimal('pickup_latitude', 10, 7)->nullable();
            $t->decimal('pickup_longitude', 10, 7)->nullable();
            $t->text('pickup_address')->nullable();
            $t->string('pickup_district')->nullable();
            $t->string('pickup_village')->nullable();
            $t->decimal('distance_km', 8, 2)->nullable();
            $t->boolean('within_radius')->nullable();
            $t->boolean('outside_radius')->default(false);
            $t->date('requested_date')->nullable();
            $t->time('requested_time_start')->nullable();
            $t->time('requested_time_end')->nullable();
            $t->timestamp('partner_proposed_time')->nullable();
            $t->string('schedule_status')->default('requested');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('declined_at')->nullable();
            $t->text('decline_reason')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->timestamps();
            $t->index(['asset_id', 'status']); });
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('handover_request_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->decimal('amount', 14, 2)->nullable();
            $t->text('note')->nullable();
            $t->dateTime('offered_at');
            $t->dateTime('expires_at');
            $t->boolean('is_current')->default(true)->index();
            $t->string('status')->default('waiting_user')->index();
            $t->string('user_rejection_reason')->nullable();
            $t->text('user_rejection_note')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->decimal('final_agreed_value', 14, 2)->nullable();
            $t->text('final_value_reason')->nullable();
            $t->timestamp('final_confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['handover_request_id', 'version']); });
        Schema::create('asset_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('event_type')->index();
            $t->string('title');
            $t->text('description')->nullable();
            $t->json('metadata_json')->nullable();
            $t->dateTime('occurred_at')->index(); });
        Schema::create('asset_custodies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('received_by_user_id')->constrained('users')->cascadeOnDelete();
            $t->dateTime('received_at');
            $t->timestamp('released_at')->nullable();
            $t->string('receive_evidence_path')->nullable();
            $t->string('release_evidence_path')->nullable();
            $t->timestamps(); });
        Schema::create('partner_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_partner_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->foreignId('to_partner_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('status')->default('pending');
            $t->text('note')->nullable();
            $t->dateTime('requested_at');
            $t->timestamp('received_at')->nullable();
            $t->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); }); }
    public function down(): void
    {
        foreach (['partner_transfers', 'asset_custodies', 'asset_events', 'offers', 'handover_requests', 'asset_assessments', 'asset_photos', 'assets'] as $x)
            Schema::dropIfExists($x); }
};
