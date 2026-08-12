<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_availability', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->constrained()->cascadeOnDelete();
            $t->enum('day_of_week', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'user_id', 'day_of_week']);
        });

        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('assigned doctor')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('type', ['WALK_IN', 'IN_PERSON', 'ONLINE', 'FOLLOW_UP', 'TREATMENT', 'IPD_REVIEW'])->index();
            $t->string('status', 24)->default('SCHEDULED')->index()->comment('SCHEDULED, CONFIRMED, CHECKED_IN, IN_CONSULTATION, COMPLETED, CANCELLED, NO_SHOW');
            $t->date('appointment_date')->index();
            $t->time('start_time');
            $t->time('end_time')->nullable();
            $t->unsignedInteger('duration_minutes')->default(15);
            $t->string('reason')->nullable();
            $t->text('notes')->nullable();
            $t->string('meeting_id')->nullable()->index()->comment('Google Meet id');
            $t->string('meeting_url')->nullable();
            $t->string('cancellation_reason')->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id', 'appointment_date']);
            $t->index(['tenant_id', 'user_id', 'appointment_date']);
            $t->index(['tenant_id', 'type', 'status', 'appointment_date'], 'appt_type_status_date_idx');
        });

        Schema::create('appointment_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->nullable()->constrained()->nullOnDelete();
            $t->string('token_number', 16)->index();
            $t->date('appointment_date')->index();
            $t->string('status', 24)->default('WAITING')->index()->comment('WAITING, CALLED, IN_PROGRESS, DONE, SKIPPED');
            $t->timestamp('called_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id', 'appointment_date', 'token_number'], 'appt_token_unique');
        });

        Schema::create('appointment_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->string('status', 24)->index();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointment_tokens');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_availability');
    }
};
