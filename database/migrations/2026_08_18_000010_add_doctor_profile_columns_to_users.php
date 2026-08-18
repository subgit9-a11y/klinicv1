<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('specialization')->nullable()->after('designation');
            $table->string('registration_number')->nullable()->index()->after('specialization');
            $table->string('medicine_system', 20)->nullable()->index()->after('registration_number');
            $table->unsignedInteger('consultation_fee_cents')->nullable()->after('medicine_system');
            $table->unsignedInteger('followup_fee_cents')->nullable()->after('consultation_fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'specialization',
                'registration_number',
                'medicine_system',
                'consultation_fee_cents',
                'followup_fee_cents',
            ]);
        });
    }
};
