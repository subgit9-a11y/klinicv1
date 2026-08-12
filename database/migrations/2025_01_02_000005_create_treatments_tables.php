<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('qualification')->nullable();
            $t->string('specialty')->nullable();
            $t->json('services')->nullable()->comment('supported treatment_service ids');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'is_active']);
        });

        Schema::create('therapist_availability', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('therapist_id')->constrained()->cascadeOnDelete();
            $t->enum('day_of_week', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'therapist_id', 'day_of_week']);
        });

        Schema::create('treatment_rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('room_number');
            $t->string('type', 32)->nullable();
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->json('supported_treatment_types')->nullable();
            $t->enum('status', ['AVAILABLE', 'OCCUPIED', 'MAINTENANCE', 'BLOCKED'])->default('AVAILABLE')->index();
            $t->timestamps();
            $t->unique(['tenant_id', 'room_number']);
        });

        Schema::create('room_availability', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_room_id')->constrained()->cascadeOnDelete();
            $t->date('date')->index();
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'treatment_room_id', 'date']);
        });

        Schema::create('treatment_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('category', 48)->nullable();
            $t->string('medicine_system', 24)->nullable()->comment('Ayurveda/Siddha/Homeopathy');
            $t->unsignedInteger('duration_minutes')->default(30);
            $t->unsignedInteger('price_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->text('description')->nullable();
            $t->boolean('requires_therapist')->default(true);
            $t->boolean('requires_room')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'category']);
        });

        Schema::create('treatment_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('therapist_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('treatment_room_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('treatment_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_package_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->date('booking_date')->index();
            $t->time('start_time');
            $t->time('end_time')->nullable();
            $t->string('status', 24)->default('BOOKED')->index()->comment('BOOKED, IN_PROGRESS, COMPLETED, CANCELLED, NO_SHOW');
            $t->string('payment_mode', 24)->default('PAY_AT_CLINIC');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
            $t->index(['tenant_id', 'therapist_id', 'booking_date']);
            $t->index(['tenant_id', 'treatment_room_id', 'booking_date']);
        });

        Schema::create('treatment_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('total_sessions')->default(0);
            $t->unsignedInteger('completed_sessions')->default(0);
            $t->string('status', 24)->default('ACTIVE')->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
        });

        Schema::create('treatment_plan_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_service_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity')->default(1);
            $t->timestamps();
        });

        Schema::create('treatment_packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('category', 48)->nullable()->comment('e.g. Panchakarma');
            $t->text('description')->nullable();
            $t->unsignedInteger('price_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->unsignedInteger('total_sessions')->default(0);
            $t->unsignedSmallInteger('validity_days')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'category']);
        });

        Schema::create('treatment_package_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('treatment_package_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_service_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity')->default(1);
            $t->timestamps();
        });

        Schema::create('treatment_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treatment_booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('therapist_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('treatment_room_id')->nullable()->constrained()->nullOnDelete();
            $t->date('session_date')->index();
            $t->time('start_time');
            $t->time('end_time')->nullable();
            $t->string('status', 24)->default('SCHEDULED')->index();
            $t->text('notes')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'treatment_booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_sessions');
        Schema::dropIfExists('treatment_package_items');
        Schema::dropIfExists('treatment_packages');
        Schema::dropIfExists('treatment_plan_items');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('treatment_bookings');
        Schema::dropIfExists('treatment_services');
        Schema::dropIfExists('room_availability');
        Schema::dropIfExists('treatment_rooms');
        Schema::dropIfExists('therapist_availability');
        Schema::dropIfExists('therapists');
    }
};
