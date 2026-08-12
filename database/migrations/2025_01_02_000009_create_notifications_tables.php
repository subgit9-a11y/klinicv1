<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Base Laravel notifications table.
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->morphs('notifiable');
            $t->string('event_id', 64)->nullable()->index()->comment('business event id');
            $t->text('data');
            $t->string('type')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('notification_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete()->comment('null = global/system template');
            $t->string('event_key', 64)->index()->comment('e.g. appointment.confirmation, ipd.admission');
            $t->string('channel', 16)->index()->comment('in_app, whatsapp, sms, email');
            $t->string('name');
            $t->string('subject')->nullable();
            $t->text('body')->nullable();
            $t->string('whatsapp_template_name')->nullable();
            $t->string('sms_template_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'event_key', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('notification_id')->nullable();
            $t->morphs('notifiable');
            $t->string('channel', 16)->index();
            $t->string('event_id', 64)->nullable()->index()->comment('notification event id');
            $t->string('recipient')->nullable();
            $t->string('status', 24)->default('PENDING')->index()->comment('PENDING, SENT, DELIVERED, FAILED');
            $t->string('provider_reference')->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notifications');
    }
};
