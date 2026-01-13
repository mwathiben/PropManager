<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('reason')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->timestamps();

            $table->index(['lease_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
