<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-50 CUSTOM-METRICS-1: per-landlord named formulas evaluated by
 * MetricFormulaService and added as derived columns to a report run.
 *
 * Formula DSL is intentionally tiny:
 *   - field references: {payment.amount}, {invoice.total_due}, ...
 *     restricted to ReportBuilderService::ALLOWED_FIELDS
 *   - numeric literals
 *   - operators: + - * / ( ) %
 *
 * NO function calls, NO variable assignment, NO unbounded recursion.
 * The expression is tokenised + parsed into RPN at write time and the
 * RPN is cached in parsed_rpn. The Phase50MetricFormulaInjectionTest
 * watchdog throws classic eval-escape payloads at every input.
 *
 * unique(landlord_id, slug) so the landlord can refer to metrics by
 * slug in their dashboards / saved reports without ambiguity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name', 200);
            $table->text('expression');
            $table->json('parsed_rpn');
            $table->string('unit', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['landlord_id', 'slug'], 'report_metrics_landlord_slug_unique');
            $table->index(['landlord_id', 'is_active'], 'report_metrics_landlord_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_metrics');
    }
};
