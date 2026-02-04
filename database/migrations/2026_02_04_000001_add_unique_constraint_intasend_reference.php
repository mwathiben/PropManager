<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAY-V2-002: Add unique constraint on intasend_reference column
 *
 * This migration adds database-level idempotency protection for IntaSend payments.
 * It mirrors PAY-V2-001 which added the same pattern for mpesa_transaction_id.
 *
 * @see docs/adr/006-payment-idempotency-pattern.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Check for existing duplicates (BLOCKING if found)
        $duplicates = DB::table('payments')
            ->select('intasend_reference', DB::raw('COUNT(*) as count'))
            ->whereNotNull('intasend_reference')
            ->groupBy('intasend_reference')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $refs = $duplicates->pluck('intasend_reference')->implode(', ');
            throw new \RuntimeException(
                "BLOCKING: Duplicate intasend_reference values found: {$refs}. "
                .'Resolve manually before running migration.'
            );
        }

        // Step 2: Drop existing non-unique index (if exists)
        $indexExists = DB::select("
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = 'payments'
            AND index_name = 'payments_intasend_ref_idx'
            LIMIT 1
        ");

        if (! empty($indexExists)) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_intasend_ref_idx');
            });
        }

        // Step 3: Add unique constraint
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('intasend_reference', 'payments_intasend_reference_unique');
        });
    }

    public function down(): void
    {
        // Remove unique constraint
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_intasend_reference_unique');
        });

        // Restore original non-unique index
        Schema::table('payments', function (Blueprint $table) {
            $table->index('intasend_reference', 'payments_intasend_ref_idx');
        });
    }
};
