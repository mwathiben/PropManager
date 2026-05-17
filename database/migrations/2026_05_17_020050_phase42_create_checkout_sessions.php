<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'submitted', 'succeeded', 'failed', 'expired'])->default('open');
            $table->unsignedBigInteger('total_amount_cents')->default(0);
            $table->json('currency_breakdown')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'status'], 'cs_landlord_status');
            $table->index(['tenant_id', 'status'], 'cs_tenant_status');
        });

        Schema::create('checkout_session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->cascadeOnDelete();
            $table->string('line_type', 64); // 'invoice' | 'add_on' | 'deposit'
            $table->unsignedBigInteger('line_id')->nullable(); // polymorphic FK (no constraint — varies by line_type)
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('description');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('stripe_payment_intent_id', 64)->nullable();
            $table->timestamps();

            $table->index(['checkout_session_id', 'currency'], 'csi_session_currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_session_items');
        Schema::dropIfExists('checkout_sessions');
    }
};
