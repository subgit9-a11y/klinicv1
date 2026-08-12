<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('k360_uid', 32)->unique()->comment('K360-P-0000012487');
            $t->string('first_name');
            $t->string('last_name')->nullable();
            $t->string('phone', 20)->index();
            $t->string('email')->nullable();
            $t->enum('gender', ['MALE', 'FEMALE', 'OTHER', 'UNKNOWN'])->default('UNKNOWN');
            $t->date('dob')->nullable();
            $t->string('blood_group', 8)->nullable();
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('pincode', 10)->nullable();
            $t->string('country_code', 4)->default('IN');
            $t->string('abha_id')->nullable()->comment('Ayushman Bharat Health Account');
            $t->text('allergies')->nullable();
            $t->text('chronic_conditions')->nullable();
            $t->text('notes')->nullable();
            $t->string('avatar')->nullable();
            $t->string('status', 16)->default('ACTIVE')->index();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'phone']);
            $t->index(['tenant_id', 'k360_uid']);
        });

        Schema::create('patient_identifiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('type', 32)->index()->comment('K360_UID, ABHA, AADHAAR, PASSPORT, CUSTOM');
            $t->string('value');
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'type', 'value']);
        });

        Schema::create('patient_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('consent_type', 48)->index()->comment('TREATMENT, TELECONSULTATION, DATA_SHARING, AI_ASSISTANCE');
            $t->boolean('granted')->default(false);
            $t->text('description')->nullable();
            $t->string('document_path')->nullable();
            $t->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('consented_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patient_identifiers');
        Schema::dropIfExists('patients');
    }
};
