<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-VACANCY-3: per-building target occupancy rate (0-100).
 * NULL = no alerting. occupancy:audit fires OccupancyTargetBreached
 * when the current occupancy_rate < target_occupancy_rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->decimal('target_occupancy_rate', 5, 2)->nullable()->after('building_type');
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn('target_occupancy_rate');
        });
    }
};
