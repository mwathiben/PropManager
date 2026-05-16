<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-34 GROWTH-MRR-1: daily MRR snapshot per (day, plan).
 *
 * Waterfall columns:
 *   - mrr_kes              = total active MRR for plan on this day
 *   - new_mrr_kes          = MRR contribution from NEW subscriptions today
 *   - expansion_mrr_kes    = MRR delta from upgrades today
 *   - contraction_mrr_kes  = MRR delta from downgrades today (negative)
 *   - churned_mrr_kes      = MRR lost from cancellations today (negative)
 *
 * Decimal(12,2) matches subscription_plans.price_monthly + invoices /
 * payments — the codebase uses KES decimals end-to-end, not cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrr_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->unsignedBigInteger('plan_id');
            $table->decimal('mrr_kes', 12, 2)->default(0);
            $table->unsignedInteger('active_subscriptions')->default(0);
            $table->decimal('new_mrr_kes', 12, 2)->default(0);
            $table->decimal('expansion_mrr_kes', 12, 2)->default(0);
            $table->decimal('contraction_mrr_kes', 12, 2)->default(0);
            $table->decimal('churned_mrr_kes', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['day', 'plan_id'], 'mrr_day_plan_uq');
            $table->index('day', 'mrr_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrr_snapshots');
    }
};
