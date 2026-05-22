<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-86 READING-INTEGRITY-1: flag implausibly high (spike) readings at entry.
 * The existing service already rejects below-previous + duplicate-date reads but
 * accepts a 10x-the-norm value silently (data-entry typo or a leak). This adds a
 * non-blocking flag the landlord sees in the review queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->boolean('is_anomalous')->default(false)->after('ocr_verified');
        });
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->dropColumn('is_anomalous');
        });
    }
};
