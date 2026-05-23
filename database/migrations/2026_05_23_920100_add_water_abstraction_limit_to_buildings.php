<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-92 WATER-COMPLIANCE: a borehole building's WRA abstraction permit allows
 * abstracting a capped volume per year. Storing the annual limit lets the
 * compliance surface compare it against actual abstraction (production). The
 * permit/quality-cert FILES + expiry live on Documents (reusing Phase-82).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->decimal('water_abstraction_limit', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn('water_abstraction_limit');
        });
    }
};
