<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_wards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type', 32)->nullable()->comment('general, private, icu');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });

        Schema::create('ipd_rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_ward_id')->constrained()->cascadeOnDelete();
            $t->string('room_number');
            $t->string('type', 32)->nullable();
            $t->string('status', 24)->default('AVAILABLE')->index();
            $t->timestamps();
            $t->unique(['tenant_id', 'room_number']);
        });

        Schema::create('ipd_beds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_room_id')->constrained()->cascadeOnDelete();
            $t->string('bed_number');
            $t->enum('status', ['AVAILABLE', 'RESERVED', 'OCCUPIED', 'CLEANING', 'MAINTENANCE', 'BLOCKED'])->default('AVAILABLE')->index();
            $t->unsignedInteger('daily_rate_cents')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'ipd_room_id', 'bed_number']);
        });

        Schema::create('ipd_admissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_bed_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('admitting_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ipd_number', 32)->nullable()->unique();
            $t->string('admission_type', 24)->default('ROUTINE')->comment('ROUTINE, EMERGENCY, TRANSFER');
            $t->text('admission_reason')->nullable();
            $t->text('provisional_diagnosis')->nullable();
            $t->timestamp('admitted_at')->index();
            $t->timestamp('discharged_at')->nullable();
            $t->string('status', 24)->default('ADMITTED')->index()->comment('ADMITTED, DISCHARGED, CANCELLED');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('ipd_daily_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('author')->nullable()->constrained()->nullOnDelete();
            $t->date('note_date')->index();
            $t->text('content');
            $t->timestamps();
            $t->index(['tenant_id', 'ipd_admission_id']);
        });

        Schema::create('ipd_vitals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('systolic_bp')->nullable();
            $t->string('diastolic_bp')->nullable();
            $t->string('pulse', 8)->nullable();
            $t->string('temperature', 8)->nullable();
            $t->string('respiratory_rate', 8)->nullable();
            $t->string('spo2', 8)->nullable();
            $t->json('custom_vitals')->nullable();
            $t->timestamp('recorded_at')->index();
            $t->timestamps();
        });

        Schema::create('ipd_nursing_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('nurse/author')->nullable()->constrained()->nullOnDelete();
            $t->text('content');
            $t->string('shift', 16)->nullable()->comment('MORNING, EVENING, NIGHT');
            $t->timestamp('recorded_at')->index();
            $t->timestamps();
        });

        Schema::create('ipd_discharge_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('discharging doctor')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('discharged_at')->index();
            $t->text('admission_diagnosis')->nullable();
            $t->text('discharge_diagnosis')->nullable();
            $t->text('treatment_given')->nullable();
            $t->text('investigations')->nullable();
            $t->text('advice_on_discharge')->nullable();
            $t->text('follow_up_instructions')->nullable();
            $t->unsignedSmallInteger('follow_up_days')->nullable();
            $t->string('status', 24)->default('DRAFT')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipd_discharge_summaries');
        Schema::dropIfExists('ipd_nursing_notes');
        Schema::dropIfExists('ipd_vitals');
        Schema::dropIfExists('ipd_daily_notes');
        Schema::dropIfExists('ipd_admissions');
        Schema::dropIfExists('ipd_beds');
        Schema::dropIfExists('ipd_rooms');
        Schema::dropIfExists('ipd_wards');
    }
};
