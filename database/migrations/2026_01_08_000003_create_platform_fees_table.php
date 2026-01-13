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
        Schema::create('platform_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users');
            $table->foreignId('payout_account_id')->nullable()
                ->constrained('landlord_payout_accounts')->nullOnDelete();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->enum('fee_type', ['transaction_percentage', 'subscription_flat', 'hybrid'])
                ->default('transaction_percentage');
            $table->decimal('fee_percentage_applied', 5, 2)->nullable();
            $table->enum('status', ['pending', 'collected', 'settled', 'failed', 'refunded'])
                ->default('pending');
            $table->string('paystack_split_reference')->nullable();
            $table->json('split_details')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fees');
    }
};
