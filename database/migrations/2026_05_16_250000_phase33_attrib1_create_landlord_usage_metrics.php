<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-33 COST-ATTRIB-1: daily roll-up of per-landlord usage counters.
 * Unique on (landlord_id, metric, day) lets the recorder DB::raw
 * upsert into a single growing counter without races. Used by:
 *   - cost:attribute cron to multiply by per-unit KES rates
 *   - ops dashboards to spot the noisy minority
 *   - quarterly per-tier pricing review
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('metric', ['db_queries', 's3_bytes', 'sms_sends', 'cron_minutes', 'log_bytes']);
            $table->date('day');
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['landlord_id', 'metric', 'day'], 'lum_landlord_metric_day_unq');
            $table->index(['metric', 'day'], 'lum_metric_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_usage_metrics');
    }
};
