<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tenancy ---
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('plan_code', 32)->default('SOLO_DOCTOR')->index();
            $t->string('status', 24)->default('TRIAL')->index();
            $t->string('system', 24)->default('AYURVEDA')->comment('primary medicine system');
            $t->string('country_code', 4)->default('IN');
            $t->string('currency', 8)->default('INR');
            $t->string('timezone')->default('Asia/Kolkata');
            $t->string('phone', 20)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->string('logo')->nullable();
            $t->string('public_slug')->nullable()->unique()->comment('for /clinic/{slug}');
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('suspended_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // Add FK from users to tenants (users.tenant_id created in base migration).
        Schema::table('users', function (Blueprint $t) {
            $t->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        // --- Plans ---
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique()->comment('SOLO_DOCTOR, SMALL_CLINIC');
            $t->string('name');
            $t->string('description')->nullable();
            $t->unsignedInteger('price_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('billing_cycle', 16)->default('MONTHLY');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('max_users')->default(1);
            $t->unsignedInteger('max_doctors')->default(1);
            $t->unsignedInteger('max_patients')->nullable();
            $t->unsignedInteger('max_appointments_per_day')->nullable();
            $t->unsignedInteger('ai_request_limit_per_day')->nullable();
            $t->boolean('ipd_enabled')->default(false);
            $t->boolean('treatments_enabled')->default(true);
            $t->json('metadata')->nullable();
            $t->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->string('feature_key', 64)->index()->comment('e.g. ipd, treatments, ai_scribe');
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('limit_value')->nullable()->comment('numeric limit; null = unlimited');
            $t->string('limit_unit', 16)->nullable()->comment('COUNT, PER_DAY, PER_MONTH');
            $t->unique(['plan_id', 'feature_key']);
            $t->timestamps();
        });

        // --- Subscriptions ---
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained();
            $t->string('status', 24)->default('ACTIVE')->index()->comment('ACTIVE, PENDING, EXPIRED, CANCELLED, PAST_DUE');
            $t->string('gateway_subscription_id')->nullable()->unique();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable()->index();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $t->string('event_type', 32)->index()->comment('CREATED, RENEWED, UPGRADED, CANCELLED, PAST_DUE');
            $t->unsignedInteger('amount_cents')->nullable();
            $t->string('currency', 8)->nullable();
            $t->string('gateway_event_id')->nullable()->unique();
            $t->json('payload')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['tenant_id']);
        });
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('tenants');
    }
};
