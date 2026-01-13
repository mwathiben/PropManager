<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->string('receipt_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method');
            $table->string('reference')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_partial')->default(false);

            $table->timestamp('issued_at');
            $table->timestamp('emailed_at')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
