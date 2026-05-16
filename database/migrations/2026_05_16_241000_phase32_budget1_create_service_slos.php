<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-32 SRE-BUDGET-1: typed per-service SLO storage. slo.md docs
 * the four tiers (tenant-facing web 99.5%, payment webhooks 99.9%,
 * background jobs best-effort, compliance same-day) but those numbers
 * lived as markdown only — ErrorBudgetCalculator needs a first-class
 * row with window_days + objective_pct + good/bad indicator metric
 * names so the calculator is refactor-resistant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_slos', function (Blueprint $table) {
            $table->id();
            $table->string('service_key', 100)->unique();
            $table->enum('tier', ['tier1', 'tier2', 'tier3', 'tier4']);
            $table->unsignedSmallInteger('window_days')->default(30);
            $table->decimal('objective_pct', 6, 3);
            $table->string('good_indicator_metric')->nullable();
            $table->string('bad_indicator_metric')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tier', 'is_active'], 'ssl_tier_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_slos');
    }
};
