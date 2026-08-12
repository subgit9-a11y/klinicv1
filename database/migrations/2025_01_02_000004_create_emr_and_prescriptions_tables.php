<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- EMR ---
        Schema::create('consultations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->nullable()->constrained()->nullOnDelete();
            $t->enum('medicine_system', ['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY'])->default('GENERAL')->index();
            $t->string('consultation_type', 24)->default('OPD')->comment('OPD, ONLINE, FOLLOW_UP, IPD');
            $t->text('chief_complaint')->nullable();
            $t->text('history')->nullable();
            $t->text('examination')->nullable();
            $t->text('assessment')->nullable();
            $t->text('diagnosis_summary')->nullable();
            $t->text('treatment_plan')->nullable();
            $t->text('advice')->nullable();
            $t->text('follow_up_instructions')->nullable();
            $t->unsignedSmallInteger('follow_up_days')->nullable();
            $t->enum('status', ['DRAFT', 'COMPLETED', 'AMENDED'])->default('DRAFT')->index();
            $t->json('system_specific')->nullable()->comment('Ayurveda/Siddha/Homeopathy fields');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
            $t->index(['tenant_id', 'user_id']);
        });

        Schema::create('clinical_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('author')->nullable()->constrained()->nullOnDelete();
            $t->string('note_type', 32)->default('PROGRESS')->comment('PROGRESS, NURSING, OBSERVATION, OTHER');
            $t->text('content');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('vitals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('systolic_bp')->nullable();
            $t->string('diastolic_bp')->nullable();
            $t->string('pulse', 8)->nullable();
            $t->string('temperature', 8)->nullable();
            $t->string('respiratory_rate', 8)->nullable();
            $t->string('spo2', 8)->nullable();
            $t->string('height', 8)->nullable();
            $t->string('weight', 8)->nullable();
            $t->string('bmi', 8)->nullable();
            $t->string('pain_score', 8)->nullable();
            $t->json('custom_vitals')->nullable();
            $t->timestamp('recorded_at')->index();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('diagnoses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code')->nullable()->comment('ICD-10 or system-specific');
            $t->string('name');
            $t->string('system', 24)->nullable()->comment('Ayurveda/Siddha/Homeopathy classification');
            $t->enum('type', ['PRIMARY', 'SECONDARY', 'DIFFERENTIAL', 'PROVISIONAL'])->default('PRIMARY');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        // --- Prescriptions ---
        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('prescriber')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 24)->default('ACTIVE')->index()->comment('ACTIVE, COMPLETED, CANCELLED, AMENDED');
            $t->text('notes')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('prescription_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $t->string('medicine')->comment('medicine/remedy');
            $t->string('form', 32)->nullable()->comment('tablet, syrup, churna, etc.');
            $t->string('strength')->nullable();
            $t->string('dose')->nullable();
            $t->string('frequency')->nullable();
            $t->string('duration')->nullable();
            $t->string('route', 32)->nullable()->comment('oral, topical, etc.');
            $t->string('quantity')->nullable();
            $t->text('instructions')->nullable();
            $t->string('timing')->nullable();
            $t->string('anupana')->nullable()->comment('Ayurveda vehicle/substance');
            $t->boolean('external_application')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('vitals');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('consultations');
    }
};
