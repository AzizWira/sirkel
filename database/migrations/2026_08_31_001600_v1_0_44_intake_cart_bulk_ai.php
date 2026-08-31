<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('intake_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 24)->index(); // standard | bulk_ai
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedTinyInteger('current_position')->default(1);
            $table->json('adaptive_questions_json')->nullable();
            $table->json('adaptive_answers_json')->nullable();
            $table->json('bulk_context_json')->nullable();
            $table->unsignedTinyInteger('question_count')->default(0);
            $table->timestamp('quota_consumed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mode', 'status']);
        });

        Schema::create('intake_session_items', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('intake_session_id')->constrained('intake_sessions')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('source', 24)->default('cart'); // cart | bulk_ai | bulk_manual
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->json('draft_answers_json')->nullable();
            $table->timestamp('assessment_completed_at')->nullable();
            $table->timestamps();
            $table->index(['intake_session_id', 'sort_order']);
            $table->index(['asset_id', 'assessment_completed_at']);
        });

        Schema::create('intake_session_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_session_id')->constrained('intake_sessions')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('ai_topup_requests', function (Blueprint $table) {
            $table->unsignedInteger('bulk_ai_quantity')->default(0)->after('condition_description_quantity');
            $table->unsignedBigInteger('bulk_ai_unit_price_idr')->default(0)->after('condition_description_unit_price_idr');
        });
    }

    public function down(): void
    {
        Schema::table('ai_topup_requests', function (Blueprint $table) {
            $table->dropColumn(['bulk_ai_quantity', 'bulk_ai_unit_price_idr']);
        });
        Schema::dropIfExists('intake_session_photos');
        Schema::dropIfExists('intake_session_items');
        Schema::dropIfExists('intake_sessions');
    }
};
