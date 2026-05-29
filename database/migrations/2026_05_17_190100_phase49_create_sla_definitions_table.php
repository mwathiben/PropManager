<?php

declare(strict_types=1);

use Database\Seeders\Phase49SlaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 SLA-PER-CATEGORY-1: per-category + per-landlord SLA overrides.
 *
 * NULL semantics:
 *   - landlord_id NULL = platform-default row (applies to all landlords)
 *   - category    NULL = matches any category for the row's (landlord, priority) tuple
 *   - subcategory NULL = matches any subcategory under the row's category
 *   - priority    NULL = matches any priority (catch-all per landlord+category)
 *
 * SlaDefinitionService::resolveFor cascades most-specific → least-specific
 * with a final fallback to Ticket::SLA_SECONDS / RESOLUTION_SLA_SECONDS
 * constants if no row matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('category', 64)->nullable();
            $table->string('subcategory', 64)->nullable();
            $table->string('priority', 16)->nullable();
            $table->integer('response_seconds');
            $table->integer('resolution_seconds');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['landlord_id', 'category', 'subcategory', 'priority'], 'sla_def_cascade_idx');
            $table->index('is_active', 'sla_def_active_idx');
        });

        // Phase-49 SLA-PER-CATEGORY-3: seed platform defaults so the
        // cascade has a working baseline immediately after migrate.
        (new Phase49SlaSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_definitions');
    }
};
