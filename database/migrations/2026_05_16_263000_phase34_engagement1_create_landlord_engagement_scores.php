<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-34 GROWTH-ENGAGEMENT-1: composite daily engagement score
 * 0-100 per landlord. components JSON captures the per-signal
 * sub-scores so the operator can see WHY a landlord dropped from
 * 80 to 35 (was it login recency? usage drop? something else?).
 *
 * Unique on (landlord_id, day) — re-running the rollup for the
 * same day overwrites with the latest computation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_engagement_scores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('landlord_id');
            $table->date('day');
            $table->unsignedTinyInteger('score');
            $table->json('components')->nullable();
            $table->timestamps();
            $table->unique(['landlord_id', 'day'], 'les_landlord_day_uq');
            $table->index('day', 'les_day_idx');
            $table->index('score', 'les_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_engagement_scores');
    }
};
