<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 audit CONC-1: enforce uniqueness of human-facing numbers at the
 * DB layer. The application-side count()+1 fallback path can race under
 * parallel webhook/cron load, producing duplicate invoice/receipt/
 * credit-note numbers. Combined with CONC-2 (InvoiceSetting RMW now
 * lockForUpdate-serialized), the unique indexes are the canonical
 * guarantee.
 *
 * Also backfills invoice_settings rows for any landlord that doesn't have
 * one, so the lockForUpdate-serialized path in InvoiceSetting is always
 * the one that runs in production — the count()+1 fallback in
 * InvoiceService/ReceiptService becomes effectively dead code.
 *
 * Migration WILL FAIL if the existing data already contains duplicates;
 * that's intentional. Duplicate financial numbers are a billing problem
 * that needs manual reconciliation before this index is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill invoice_settings for any landlord missing one. Uses
        // INSERT ... SELECT so it's safe to re-run (the unique(landlord_id)
        // index on invoice_settings de-duplicates).
        DB::statement(<<<'SQL'
            INSERT IGNORE INTO invoice_settings (
                landlord_id,
                invoice_prefix, invoice_next_number,
                receipt_prefix, receipt_next_number,
                credit_note_prefix, credit_note_next_number,
                default_due_days, late_penalty_percentage, grace_period_days,
                auto_generate_enabled, auto_generate_day, auto_send_enabled,
                created_at, updated_at
            )
            SELECT
                u.id,
                'INV', 1,
                'RCT', 1,
                'CN',  1,
                7, 0, 0,
                0, 1, 0,
                NOW(), NOW()
            FROM users u
            LEFT JOIN invoice_settings s ON s.landlord_id = u.id
            WHERE u.role = 'landlord'
              AND s.id IS NULL
        SQL);

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_invoice_number_unique')) {
                $table->unique('invoice_number', 'invoices_invoice_number_unique');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasIndex('receipts', 'receipts_receipt_number_unique')) {
                $table->unique('receipt_number', 'receipts_receipt_number_unique');
            }
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            if (! Schema::hasIndex('credit_notes', 'credit_notes_credit_number_unique')) {
                $table->unique('credit_number', 'credit_notes_credit_number_unique');
            }
        });

        // CONC-6: prevent duplicate same-day late fees on the same invoice
        // for non-compounding policies. The lockForUpdate+re-check inside
        // applyLateFee is the primary guard; this index is the canonical
        // belt-and-braces backstop at the DB layer.
        Schema::table('late_fees', function (Blueprint $table) {
            if (! Schema::hasIndex('late_fees', 'late_fees_invoice_applied_unique')) {
                $table->unique(['invoice_id', 'applied_date'], 'late_fees_invoice_applied_unique');
            }
        });

        // CONC-12: at most one credit AND one debit per payment_id. Closes
        // the wallet-double-credit replay vector. NULL payment_id rows
        // (manual wallet adjustments) are not affected — MySQL UNIQUE
        // treats NULL != NULL.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasIndex('wallet_transactions', 'wallet_transactions_payment_type_unique')) {
                $table->unique(['payment_id', 'type'], 'wallet_transactions_payment_type_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasIndex('invoices', 'invoices_invoice_number_unique')) {
                $table->dropUnique('invoices_invoice_number_unique');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            if (Schema::hasIndex('receipts', 'receipts_receipt_number_unique')) {
                $table->dropUnique('receipts_receipt_number_unique');
            }
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            if (Schema::hasIndex('credit_notes', 'credit_notes_credit_number_unique')) {
                $table->dropUnique('credit_notes_credit_number_unique');
            }
        });

        Schema::table('late_fees', function (Blueprint $table) {
            if (Schema::hasIndex('late_fees', 'late_fees_invoice_applied_unique')) {
                $table->dropUnique('late_fees_invoice_applied_unique');
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasIndex('wallet_transactions', 'wallet_transactions_payment_type_unique')) {
                $table->dropUnique('wallet_transactions_payment_type_unique');
            }
        });

        // Backfilled invoice_settings rows are intentionally NOT removed on
        // rollback — they're correct defaults that should persist.
    }
};
