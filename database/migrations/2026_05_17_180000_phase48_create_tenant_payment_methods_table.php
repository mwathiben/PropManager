<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-48 TENANT-PAYMENT-METHOD-1: stored M-Pesa / bank / card credentials
 * for tenants to enable auto-debit + recurring payments.
 *
 * details_encrypted is JSON cast through Laravel's encrypted:json model
 * cast (Crypt-backed) — type-specific shape:
 *   - mpesa: {phone: "0712345678"}
 *   - bank:  {bank_name, account_number, account_name}
 *   - card:  {brand, last4, stripe_payment_method_id}
 *
 * Soft-deletes per DPA-3 retention pattern — a tenant who exercises
 * right-to-be-forgotten gets their row soft-deleted but the audit log
 * remains for the legal-obligation retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mpesa', 'bank', 'card']);
            // Laravel's encrypted:json cast writes Crypt-encrypted text;
            // not JSON syntax, so MySQL's json type rejects it. Use text.
            $table->text('details_encrypted');
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'type'], 'tenant_payment_methods_user_type_unique');
            $table->index(['user_id', 'is_default'], 'tenant_payment_methods_user_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_methods');
    }
};
