<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-30 INT-MPESA-DEEP-1/2: track every M-Pesa B2C payout we
 * initiate so the polymorphic source (deposit_refund_requests,
 * future use cases) can be reconciled. The Daraja API responds
 * asynchronously to /b2c/v1/paymentrequest — we get a
 * ConversationID + OriginatorConversationID back immediately but
 * the actual payout result arrives over an out-of-band ResultURL
 * callback (or never, if our callback URL is unreachable). The
 * mpesa:reconcile-status cron walks rows with status='sent' that
 * are older than 5 minutes and asks Daraja for the canonical
 * status, closing the silent-failure gap.
 *
 * Status machine: queued -> sent (Daraja accepted) -> succeeded |
 * failed | timed_out (Daraja confirmed via callback or our poll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_b2c_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('phone', 32);
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 128);
            $table->string('remarks', 255)->nullable();
            $table->enum('status', ['queued', 'sent', 'succeeded', 'failed', 'timed_out'])->default('queued');
            $table->string('originator_conversation_id', 128)->nullable();
            $table->string('conversation_id', 128)->nullable();
            $table->string('transaction_id', 128)->nullable();
            $table->json('last_response')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'mb2c_source_idx');
            $table->index(['landlord_id', 'status', 'sent_at'], 'mb2c_landlord_status_idx');
            $table->unique('originator_conversation_id', 'mb2c_originator_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_b2c_requests');
    }
};
