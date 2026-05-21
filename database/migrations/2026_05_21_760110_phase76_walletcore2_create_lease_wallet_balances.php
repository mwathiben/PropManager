<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-76 WALLET-DEEP MULTI-CCY-1: per-(lease, currency) cached wallet balance
 * for NON-DEFAULT currencies. The landlord's default-currency balance stays in
 * the legacy Lease.wallet_balance scalar (source of truth for that currency, so
 * the ~10 existing readers keep working); non-default currencies live here.
 * WalletService is the only writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency', 3);
            // Matches leases.wallet_balance + wallet_transactions.balance_after
            // (decimal 10,2) so the cache row and its ledger snapshot agree.
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['lease_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_wallet_balances');
    }
};
