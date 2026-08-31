<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('handover_request_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category');
            $t->text('description');
            $t->enum('status', ['open', 'in_review', 'resolved', 'dismissed'])->default('open')->index();
            $t->text('admin_note')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps(); });
        Schema::create('ai_usage_logs', function (Blueprint $t) {
            $t->id();
            $t->string('feature')->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('partner_profile_id')->nullable()->constrained()->nullOnDelete();
            $t->string('model');
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('cached_input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('estimated_cost_usd', 14, 8)->default(0);
            $t->unsignedInteger('latency_ms')->nullable();
            $t->string('status')->index();
            $t->string('request_hash', 64)->nullable()->index();
            $t->text('error_message')->nullable();
            $t->timestamps(); });
        Schema::create('ai_results', function (Blueprint $t) {
            $t->id();
            $t->string('feature')->index();
            $t->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('input_hash', 64)->index();
            $t->string('model');
            $t->json('result_json');
            $t->decimal('confidence', 5, 4)->nullable();
            $t->timestamp('stale_at')->nullable();
            $t->timestamps();
            $t->unique(['feature', 'asset_id', 'input_hash']); });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->dateTime('created_at')->index();
            $t->index(['auditable_type', 'auditable_id']); });
        Schema::create('system_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->string('group')->default('general')->index();
            $t->timestamps(); });
        Schema::create('email_otp_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->dateTime('expires_at')->index();
            $t->dateTime('sent_at');
            $t->timestamp('verified_at')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamps(); }); }
    public function down(): void
    {
        foreach (['email_otp_codes', 'system_settings', 'audit_logs', 'ai_results', 'ai_usage_logs', 'issue_reports'] as $x)
            Schema::dropIfExists($x); }
};
