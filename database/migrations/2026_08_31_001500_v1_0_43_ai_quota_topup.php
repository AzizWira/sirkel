<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedInteger('asset_intake_quantity')->default(0);
            $table->unsignedInteger('condition_description_quantity')->default(0);
            $table->unsignedBigInteger('asset_intake_unit_price_idr')->default(0);
            $table->unsignedBigInteger('condition_description_unit_price_idr')->default(0);
            $table->unsignedBigInteger('total_amount_idr')->default(0);
            $table->text('whatsapp_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_topup_requests');
    }
};
