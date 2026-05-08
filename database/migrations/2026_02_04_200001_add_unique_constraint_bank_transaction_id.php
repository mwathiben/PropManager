<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAY-V2-003 Gap 2: Add unique constraint on bank_transaction_id.
 *
 * This prevents duplicate bank payments from being created when
 * webhooks are retried or delivered multiple times (Equity, KCB, Coop).
 *
 * Two-layer idempotency architecture:
 * 1. Application layer: IdempotencyService.acquire() - early detection
 * 2. Database layer: UNIQUE constraint - safety net for race conditions
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check for existing duplicates BEFORE adding constraint
        $duplicates = DB::table('payments')
            ->select('bank_transaction_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('bank_transaction_id')
            ->groupBy('bank_transaction_id')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $ids = $duplicates->pluck('bank_transaction_id')->implode(', ');
            throw new \RuntimeException(
                "Cannot add unique constraint: duplicate bank_transaction_id values exist.\n".
                "Resolve these duplicates first: [{$ids}]\n".
                'Query to find details: SELECT * FROM payments WHERE bank_transaction_id IN ('.$ids.')'
            );
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('bank_transaction_id', 'payments_bank_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_bank_transaction_id_unique');
        });
    }
};
