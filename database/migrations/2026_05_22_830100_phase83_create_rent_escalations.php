<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-83 RENT-ESCALATION-1: scheduled rent increases. Before this, rent only
 * changed via the manual adjustRent/batchAdjustRent paths (logged to
 * rent_histories) and auto-renew copied the rent flat. This table lets a
 * landlord schedule a future increase (percentage or fixed amount) that the
 * rent:apply-escalations cron applies on its effective_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('escalation_type', ['percentage', 'fixed_amount']);
            // percentage value (e.g. 10.00 = +10%) OR a fixed KES step.
            $table->decimal('amount', 10, 2);
            $table->date('effective_date');
            $table->enum('status', ['scheduled', 'applied', 'cancelled'])->default('scheduled');
            $table->timestamp('applied_at')->nullable();
            // The resulting rent at apply time + the audit row it wrote.
            $table->decimal('new_rent_amount', 10, 2)->nullable();
            $table->foreignId('rent_history_id')->nullable()->constrained('rent_histories')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The apply cron scans scheduled escalations whose date has arrived.
            $table->index(['status', 'effective_date'], 'rent_escalations_due_idx');
            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_escalations');
    }
};
