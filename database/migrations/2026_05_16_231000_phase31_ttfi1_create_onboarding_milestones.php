<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-31 ONB-TTFI-1: per-landlord activation funnel ledger. Records
 * the FIRST occurrence of each milestone in the activation funnel
 * (signed_up -> first_property -> first_unit -> first_tenant ->
 *  first_invoice -> first_payment). Unique on (landlord_id, milestone)
 * makes the recorder write-once idempotent.
 *
 * activation:audit derives every Prometheus gauge from this table:
 *   - activation_signups_count{period}
 *   - activation_milestone_count{milestone, period}
 *   - activation_time_to_first_invoice_p50_hours
 *   - activation_time_to_first_invoice_p90_hours
 *
 * The metadata json column captures the seed row's primary key so a
 * future audit can re-derive the trace (e.g. metadata.invoice_id for
 * first_invoice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('milestone', [
                'signed_up',
                'first_property',
                'first_unit',
                'first_tenant',
                'first_invoice',
                'first_payment',
            ]);
            $table->timestamp('reached_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'milestone'], 'om_landlord_milestone_unq');
            $table->index(['milestone', 'reached_at'], 'om_milestone_reached_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_milestones');
    }
};
