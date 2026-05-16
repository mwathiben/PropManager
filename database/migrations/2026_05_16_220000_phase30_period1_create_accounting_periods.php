<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-30 INT-PERIOD-LOCK-1: per-landlord accounting period registry.
 * Once a month closes (status=closed, closed_at set), no Invoice /
 * Payment / Expense whose effective date falls inside the window can
 * be created, updated, or deleted — the model boot hooks throw
 * AccountingPeriodLockedException. This is what makes a "closed month"
 * trustworthy: an accountant can sign off on a P&L knowing it can't
 * silently mutate underneath them.
 *
 * Months are stored as the first day of the month (period_start) +
 * inclusive last day (period_end). closed_by_user_id records who
 * signed off; close_notes captures the reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_notes')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'period_start'], 'ap_landlord_start_unq');
            $table->index(['landlord_id', 'status', 'period_start'], 'ap_landlord_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
