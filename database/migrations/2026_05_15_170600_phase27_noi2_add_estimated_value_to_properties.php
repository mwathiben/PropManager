<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-27 BI-NOI-2: cap-rate calculator needs an `estimated_value`
 * for each property (NOI / value = cap rate %). Nullable so existing
 * landlords aren't forced to estimate values before using NOI/byProperty
 * — pages that need cap-rate gracefully render N/A when the value is
 * null.
 *
 * Stored on properties (not buildings) because cap-rate is a property-
 * level investment metric; a multi-building property has one
 * acquisition value. If a landlord later wants per-building values,
 * a Phase-N migration can add it without conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'estimated_value')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->decimal('estimated_value', 14, 2)->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('properties', 'estimated_value')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('estimated_value');
            });
        }
    }
};
