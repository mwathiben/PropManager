<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add UNIQUE constraint on mpesa_transaction_id column
 *
 * This migration upgrades the regular index on mpesa_transaction_id to a UNIQUE constraint
 * to provide database-level idempotency protection. This prevents duplicate M-Pesa payments
 * even under high concurrency, complementing application-level checks.
 *
 * @see docs/adr/006-payment-idempotency-pattern.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Check for existing duplicates - FAIL if any found
        // This is critical: we cannot add a unique constraint if duplicates exist
        $duplicates = DB::table('payments')
            ->select('mpesa_transaction_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('mpesa_transaction_id')
            ->groupBy('mpesa_transaction_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $ids = $duplicates->pluck('mpesa_transaction_id')->implode(', ');
            throw new RuntimeException(
                "BLOCKING: Cannot add unique constraint - duplicate mpesa_transaction_id values found: [{$ids}]. "
                .'Please resolve these duplicates manually before running this migration. '
                .'Query to find duplicates: SELECT mpesa_transaction_id, COUNT(*) as cnt FROM payments '
                .'WHERE mpesa_transaction_id IS NOT NULL GROUP BY mpesa_transaction_id HAVING cnt > 1'
            );
        }

        // Step 2: Drop existing non-unique index
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_mpesa_transaction_idx');
        });

        // Step 3: Add unique constraint (allows NULL values - MySQL behavior)
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('mpesa_transaction_id', 'payments_mpesa_transaction_id_unique');
        });
    }

    public function down(): void
    {
        // Rollback: restore non-unique index
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_mpesa_transaction_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('mpesa_transaction_id', 'payments_mpesa_transaction_idx');
        });
    }
};
