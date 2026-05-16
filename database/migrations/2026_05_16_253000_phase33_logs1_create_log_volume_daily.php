<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-33 COST-LOGS-1: per-landlord-per-day log volume.
 * Recorder upserts (landlord_id, day) via INSERT...ON DUPLICATE
 * KEY UPDATE — atomic counter, no read-modify-write race.
 *
 * Same shape as landlord_usage_metrics but separates byte_count
 * from line_count (one log line averages ~500B but spikes from
 * stack-trace dumps blow that wide, so both signals matter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_volume_daily', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('landlord_id');
            $table->date('day');
            $table->unsignedBigInteger('byte_count')->default(0);
            $table->unsignedBigInteger('line_count')->default(0);
            $table->timestamps();
            $table->unique(['landlord_id', 'day'], 'lvd_landlord_day_uq');
            $table->index('day', 'lvd_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_volume_daily');
    }
};
