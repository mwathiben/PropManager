<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queued_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('payment_method', 20);
            $table->string('phone_number', 20)->nullable();

            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['landlord_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_payment_intents');
    }
};
