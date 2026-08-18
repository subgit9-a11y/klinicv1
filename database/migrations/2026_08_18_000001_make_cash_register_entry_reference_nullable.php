<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opening floats and manual cash adjustments have no polymorphic
        // reference, so the morph columns must be nullable (morphs() is NOT NULL).
        Schema::table('cash_register_entries', function (Blueprint $t) {
            $t->string('reference_type')->nullable()->change();
            $t->unsignedBigInteger('reference_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_entries', function (Blueprint $t) {
            $t->string('reference_type')->nullable(false)->change();
            $t->unsignedBigInteger('reference_id')->nullable(false)->change();
        });
    }
};
