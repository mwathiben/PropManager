<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intasend_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            // IntaSend tracking fields
            $table->string('intasend_invoice_id', 50)->nullable()->unique();
            $table->string('api_ref', 100)->index();
            $table->string('phone_number', 20);

            // Amounts
            $table->decimal('amount', 12, 2);
            $table->decimal('intasend_charges', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->nullable();

            // Split tracking (platform fee)
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('landlord_amount', 12, 2)->nullable();

            // Status
            $table->string('state', 20)->default('PENDING');
            $table->string('mpesa_receipt', 50)->nullable();
            $table->text('failure_reason')->nullable();

            // Webhook data
            $table->json('webhook_payload')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('state');
            $table->index(['landlord_id', 'state']);
            $table->index(['invoice_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intasend_transactions');
    }
};
