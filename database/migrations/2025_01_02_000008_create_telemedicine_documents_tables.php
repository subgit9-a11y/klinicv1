<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teleconsultations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->nullable()->constrained()->nullOnDelete();
            $t->string('meeting_id')->nullable()->index();
            $t->string('meeting_url')->nullable();
            $t->string('status', 24)->default('SCHEDULED')->index();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->enum('type', ['LAB_REPORT', 'IMAGING', 'PRESCRIPTION', 'DISCHARGE_SUMMARY', 'CONSENT', 'REFERRAL', 'CLINICAL', 'OTHER'])->default('OTHER');
            $t->string('disk', 16)->default('local');
            $t->string('path');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->json('metadata')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id', 'type']);
        });

        Schema::create('followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->date('due_date')->index();
            $t->string('status', 24)->default('PENDING')->index()->comment('PENDING, SCHEDULED, COMPLETED, MISSED');
            $t->text('instructions')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id', 'due_date']);
        });

        Schema::create('investigations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('category', 48)->nullable();
            $t->string('status', 24)->default('REQUESTED')->index()->comment('REQUESTED, SAMPLE_COLLECTED, COMPLETED, CANCELLED');
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('investigation_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('parameter')->nullable();
            $t->string('value')->nullable();
            $t->string('unit')->nullable();
            $t->string('reference_range')->nullable();
            $t->string('flag', 16)->nullable()->comment('NORMAL, HIGH, LOW, CRITICAL');
            $t->text('notes')->nullable();
            $t->timestamp('resulted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_results');
        Schema::dropIfExists('investigations');
        Schema::dropIfExists('followups');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('teleconsultations');
    }
};
