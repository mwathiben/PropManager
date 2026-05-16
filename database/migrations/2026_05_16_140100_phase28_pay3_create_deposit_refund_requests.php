<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-28 TENANT-PAY-3: deposit refund request flow.
 *
 * Status machine: submitted → under_review → (approved | rejected) →
 * paid. Approved + paid carry final_amount_cents (may differ from
 * requested when landlord deducts move-out fees). payment_method +
 * payment_details JSON captures mpesa phone / bank account / cheque
 * recipient so payout can be processed off-platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_amount_cents');
            $table->enum('payment_method', ['mpesa', 'bank_transfer', 'cheque']);
            $table->json('payment_details');
            $table->enum('status', ['submitted', 'under_review', 'approved', 'rejected', 'paid'])
                ->default('submitted');
            $table->unsignedBigInteger('final_amount_cents')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index(['lease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_refund_requests');
    }
};
