<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('landlord_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'processing', 'completed', 'failed', 'cancelled']);
            $table->string('reason');
            $table->string('payment_method');

            // Gateway-specific references
            $table->string('paystack_refund_reference')->nullable();
            $table->string('mpesa_conversation_id')->nullable();
            $table->string('mpesa_transaction_id')->nullable();

            // Audit trail
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index('paystack_refund_reference');
            $table->index('mpesa_conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
