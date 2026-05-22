<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-85 DISPUTE-1: first-class chargeback/dispute record. Before this, Stripe
 * disputes were only logged to operational_incidents (ops-facing) — the landlord
 * (whose money is at risk) had no record, notification, or view. NO auto-reversal
 * of the Payment/Invoice: disputes can be won, so resolution is tracked, not
 * pre-emptively applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('gateway_dispute_id')->unique();
            $table->string('charge_reference')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->string('reason')->nullable();
            $table->enum('status', ['open', 'under_review', 'won', 'lost', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
    }
};
