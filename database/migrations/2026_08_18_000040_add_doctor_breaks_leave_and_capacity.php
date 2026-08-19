<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends doctor availability to support intra-day breaks (lunch),
 * dated leave/holidays, per-doctor consultation duration, and a
 * per-day appointment capacity cap — addressing review item #18.
 *
 * The slot engine (AppointmentService::availableSlots) previously
 * generated uniform slots across an entire working block, so it could
 * offer a slot during a doctor's lunch and during approved leave.
 * These additions are all nullable / opt-in so existing rows are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_availability', function (Blueprint $t) {
            // Optional intra-day break window (e.g. lunch 13:00-14:00).
            // Slots are never generated inside [break_start, break_end).
            $t->time('break_start_time')->nullable()->after('end_time');
            $t->time('break_end_time')->nullable()->after('break_start_time');
        });

        Schema::create('doctor_leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->constrained()->cascadeOnDelete();
            // The inclusive date range the doctor is unavailable.
            $t->date('start_date');
            $t->date('end_date');
            $t->string('reason')->nullable();
            $t->enum('type', ['LEAVE', 'HOLIDAY', 'EMERGENCY', 'OTHER'])->default('LEAVE');
            $t->boolean('is_approved')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'user_id']);
            $t->index(['start_date', 'end_date']);
        });

        Schema::table('users', function (Blueprint $t) {
            // Per-doctor slot length; falls back to the system default when null.
            $t->unsignedInteger('consultation_duration_minutes')->nullable()->after('followup_fee_cents');
            // Hard daily cap on appointments per doctor (null = unlimited).
            $t->unsignedInteger('max_daily_appointments')->nullable()->after('consultation_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['consultation_duration_minutes', 'max_daily_appointments']);
        });

        Schema::dropIfExists('doctor_leaves');

        Schema::table('doctor_availability', function (Blueprint $t) {
            $t->dropColumn(['break_start_time', 'break_end_time']);
        });
    }
};
