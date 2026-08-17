<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('provider', 32)->index()->comment('cashfree, google_meet, whatsapp, msg91, resend, s3, gemini');
            $t->string('name');
            $t->json('credentials_encrypted')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'provider']);
        });

        Schema::create('feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->unique();
            $t->string('description')->nullable();
            $t->boolean('is_global')->default(false);
            $t->boolean('default_enabled')->default(false);
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->timestamps();
            $t->index(['tenant_id', 'key']);
        });

        Schema::create('custom_fields', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('entity', 32)->index()->comment('patient, consultation, appointment, etc.');
            $t->string('label');
            $t->string('field_key', 64);
            $t->string('type', 24)->default('TEXT')->comment('TEXT, NUMBER, DATE, SELECT, BOOLEAN, JSON');
            $t->json('options')->nullable();
            $t->boolean('is_required')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'entity', 'field_key']);
        });

        Schema::create('custom_field_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $t->morphs('entity');
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['custom_field_id', 'entity_type', 'entity_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('action', 64)->index()->comment('auth.login, patient.access, payment.refund, etc.');
            $t->string('category', 32)->nullable()->index()->comment('AUTH, PATIENT, CLINICAL, PRESCRIPTION, TREATMENT, IPD, BILLING, PAYMENT, AI, DOCUMENT, PERMISSION, SUPER_ADMIN');
            $t->nullableMorphs('auditable');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'category', 'action']);
        });

        Schema::create('system_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 128)->unique();
            $t->text('value')->nullable();
            $t->string('category', 48)->nullable()->index();
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key', 128)->index();
            $t->text('value')->nullable();
            $t->string('category', 48)->nullable()->index();
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('integration_accounts');
    }
};
