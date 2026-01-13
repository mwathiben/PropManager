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
        Schema::create('deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', [
                'received',
                'partial_refund',
                'full_refund',
                'deduction',
                'forfeit',
                'transfer',
            ]);

            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);

            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();

            $table->foreignId('move_out_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['lease_id', 'type']);
            $table->index(['landlord_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_transactions');
    }
};
