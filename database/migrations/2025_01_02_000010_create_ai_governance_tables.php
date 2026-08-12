<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_features', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->unique()->comment('ai_scribe, patient_summary, etc.');
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('category', 48)->nullable()->comment('CLINICAL, ASSISTANT');
            $t->string('default_prompt_key', 64)->nullable();
            $t->string('default_model', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32)->default('gemini')->index();
            $t->string('model_id', 64)->comment('e.g. gemini-2.0-flash');
            $t->string('display_name');
            $t->string('context_window')->nullable();
            $t->boolean('supports_vision')->default(false);
            $t->boolean('supports_structured')->default(false);
            $t->unsignedInteger('input_cost_per_million_cents')->default(0);
            $t->unsignedInteger('output_cost_per_million_cents')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['provider', 'model_id']);
        });

        Schema::create('ai_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->index()->comment('matches ai_feature.default_prompt_key');
            $t->string('name');
            $t->string('system', 24)->default('GENERAL')->comment('GENERAL, AYURVEDA, SIDDHA, HOMEOPATHY');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('ai_prompt_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ai_prompt_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->text('system_prompt')->nullable();
            $t->text('user_prompt_template');
            $t->json('expected_output_schema')->nullable();
            $t->string('default_model', 64)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['ai_prompt_id', 'version']);
        });

        Schema::create('ai_provider_credentials', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32)->index()->comment('gemini');
            $t->string('name');
            $t->text('api_key_encrypted');
            $t->string('default_model', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('ai_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ai_feature_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ai_prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider', 32)->nullable();
            $t->string('model', 64)->nullable();
            $t->morphs('contextable');
            $t->text('input_summary')->nullable()->comment('minimal; no unnecessary patient data');
            $t->longText('output')->nullable();
            $t->string('output_status', 24)->default('PENDING')->index()->comment('PENDING, DRAFT, APPROVED, REJECTED, ERROR');
            $t->string('status', 24)->default('PENDING')->index()->comment('PENDING, SUCCESS, ERROR');
            $t->unsignedInteger('input_tokens')->nullable();
            $t->unsignedInteger('output_tokens')->nullable();
            $t->unsignedInteger('estimated_cost_cents')->default(0);
            $t->unsignedInteger('duration_ms')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_provider_credentials');
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_features');
    }
};
