<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-35 PLATFORM-BILLING-1: append-only audit trail of every
 * plan change.
 *
 * Two flavours:
 *   - Immediate: effective_at set on insert, scheduled_for NULL.
 *     Phase-34 MrrSnapshotService reads these on day D to populate
 *     expansion_mrr_kes / contraction_mrr_kes columns (waterfall fix).
 *   - Scheduled downgrade: scheduled_for=sub.current_period_end on
 *     insert, effective_at NULL until the subscriptions:apply-
 *     downgrades cron flips it on the period boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('from_plan_id');
            $table->unsignedBigInteger('to_plan_id');
            $table->enum('change_type', ['upgrade', 'downgrade', 'same']);
            $table->decimal('prorated_amount_kes', 12, 2)->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'created_at'], 'sc_sub_created_idx');
            $table->index('scheduled_for', 'sc_scheduled_idx');
            $table->index('effective_at', 'sc_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
