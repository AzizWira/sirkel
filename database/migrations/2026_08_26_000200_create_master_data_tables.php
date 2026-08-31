<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_groups', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps(); });
        Schema::create('device_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_group_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('supports_batch')->default(false);
            $t->boolean('special_handling_possible')->default(false);
            $t->boolean('active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps(); });
        Schema::create('partner_accepted_categories', function (Blueprint $t) {
            $t->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_category_id')->constrained()->cascadeOnDelete();
            $t->primary(['partner_profile_id', 'device_category_id']); });
        Schema::create('regions', function (Blueprint $t) {
            $t->id();
            $t->string('external_id')->nullable()->index();
            $t->string('parent_external_id')->nullable()->index();
            $t->string('level')->index();
            $t->string('name');
            $t->string('province')->nullable();
            $t->string('city')->nullable()->index();
            $t->string('district')->nullable()->index();
            $t->string('village')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps(); });
        Schema::create('questionnaire_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps(); });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('questionnaire_template_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->text('text');
            $t->enum('type', ['single', 'multi', 'text', 'boolean']);
            $t->boolean('required')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('show_when_json')->nullable();
            $t->text('help_text')->nullable();
            $t->timestamps(); });
        Schema::create('question_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->string('value');
            $t->string('label');
            $t->integer('sort_order')->default(0);
            $t->json('flags_json')->nullable();
            $t->timestamps(); });
        Schema::create('circular_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('priority')->default(100)->index();
            $t->boolean('active')->default(true);
            $t->json('conditions_json');
            $t->string('result_path');
            $t->text('explanation_template')->nullable();
            $t->timestamps(); }); }
    public function down(): void
    {
        foreach (['circular_rules', 'question_options', 'questions', 'questionnaire_templates', 'regions', 'partner_accepted_categories', 'device_categories', 'device_groups'] as $x)
            Schema::dropIfExists($x); }
};
