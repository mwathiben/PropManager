<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-50 LANDLORD-DASHBOARDS-1: composable dashboards each landlord
 * assembles from their SavedReport library + ReportMetric definitions.
 *
 * layout JSON shape:
 *   [
 *     {
 *       "type": "saved_report",
 *       "saved_report_id": 7,
 *       "title": "Outstanding rent",
 *       "size": "wide" | "narrow"
 *     },
 *     {
 *       "type": "metric",
 *       "metric_slug": "collection_rate",
 *       "saved_report_id": 7,
 *       "title": "Collection rate (avg)"
 *     }
 *   ]
 *
 * Validation lives in DashboardService::buildPayload — saved_report_id
 * and metric_slug are re-checked for landlord ownership at render time.
 * The migration stores opaque JSON to keep schema migration burden low
 * across layout-shape evolution; never trust the JSON on read.
 *
 * unique(landlord_id, slug) keeps URLs stable + collision-free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('layout');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['landlord_id', 'slug'], 'landlord_dashboards_slug_unique');
            $table->index(['landlord_id', 'is_default'], 'landlord_dashboards_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_dashboards');
    }
};
