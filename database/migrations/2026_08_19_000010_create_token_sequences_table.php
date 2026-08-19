<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-doctor per-day token counter rows. The queue token sequence used to
 * compute MAX(token_number)+1 under lockForUpdate on the day's tokens —
 * which locks nothing when no token exists yet for that doctor/day. A
 * dedicated counter row (one per tenant+doctor+date) is created on first
 * use and then row-locked, so the FIRST walk-in of the day cannot race.
 *
 * The unique index guarantees a single counter row per (tenant, doctor,
 * date); the appt_token_unique constraint on appointment_tokens remains the
 * final arbiter for token numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->comment('doctor')->constrained()->cascadeOnDelete();
            $t->date('token_date');
            $t->unsignedInteger('next_number')->default(1);
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id', 'token_date'], 'token_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_sequences');
    }
};
