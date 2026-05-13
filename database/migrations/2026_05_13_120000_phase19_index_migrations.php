<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-19 index additions — combined to minimise CI migration time:
 *
 *   INDEX-2: late_fees (landlord_id, is_waived, applied_date) +
 *            (is_waived, invoice_id, fee_amount). Covers Phase-15
 *            slow-query #3 "active fees for landlord X in date range"
 *            AND Phase-19 INDEX-1 (latefees:audit-drift) GROUP BY path.
 *
 *   INDEX-3: invoice_items (invoice_id) leading index — InnoDB needs
 *            a leading FK index for reverse joins + correct lock scope.
 *            ->constrained() in Laravel does not auto-add this.
 *
 *   INDEX-4 (DATA-8 closure): TenantScope composite for tables that
 *            previously lacked the (landlord_id, primary_date) pattern
 *            established by Phase-15 PERF-1/2/7:
 *              buildings (landlord_id, id)
 *              units     (landlord_id, id)
 *              properties (landlord_id, created_at)
 *              wallet_transactions (landlord_id, created_at) [INDEX-8]
 *
 *   INDEX-5: invoices (landlord_id, status, due_date, total_due,
 *            amount_paid) covering index. Replaces the Phase-15 PERF-2
 *            (landlord_id, status, due_date) which becomes a prefix of
 *            the new covering index — explicitly dropped to avoid
 *            duplicate/redundant indexes. Arrears + revenue report
 *            queries serve entirely from index leaf pages.
 *
 *   INDEX-7: expenses leading FK indexes for category_id, vendor_id,
 *            property_id, building_id, unit_id. The existing
 *            (landlord_id, expense_date) + (landlord_id, category_id)
 *            composites only seek on (landlord_id, ...) prefix; a
 *            "list expenses by vendor X" or "by category Y" path
 *            scanned the table pre-Phase-19.
 *
 * All operations are InnoDB-online (CREATE INDEX ALGORITHM=INPLACE
 * LOCK=NONE) so the migration does not require a maintenance window.
 * Each block is idempotent via Schema::hasIndex.
 */
return new class extends Migration
{
    public function up(): void
    {
        // INDEX-2: late_fees active-subset composites.
        Schema::table('late_fees', function (Blueprint $table) {
            if (! Schema::hasIndex('late_fees', 'late_fees_landlord_active_date_idx')) {
                $table->index(['landlord_id', 'is_waived', 'applied_date'], 'late_fees_landlord_active_date_idx');
            }
            if (! Schema::hasIndex('late_fees', 'late_fees_active_invoice_fee_idx')) {
                $table->index(['is_waived', 'invoice_id', 'fee_amount'], 'late_fees_active_invoice_fee_idx');
            }
        });

        // INDEX-3: invoice_items leading FK index.
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasIndex('invoice_items', 'invoice_items_invoice_id_idx')) {
                $table->index('invoice_id', 'invoice_items_invoice_id_idx');
            }
        });

        // INDEX-4 / DATA-8 closure: TenantScope composites.
        Schema::table('buildings', function (Blueprint $table) {
            if (! Schema::hasIndex('buildings', 'buildings_landlord_id_idx')) {
                $table->index(['landlord_id', 'id'], 'buildings_landlord_id_idx');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasIndex('units', 'units_landlord_id_idx')) {
                $table->index(['landlord_id', 'id'], 'units_landlord_id_idx');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasIndex('properties', 'properties_landlord_created_idx')) {
                $table->index(['landlord_id', 'created_at'], 'properties_landlord_created_idx');
            }
        });

        // INDEX-8 (folded into INDEX-4): wallet_transactions composite.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasIndex('wallet_transactions', 'wallet_transactions_landlord_created_idx')) {
                $table->index(['landlord_id', 'created_at'], 'wallet_transactions_landlord_created_idx');
            }
        });

        // INDEX-5: invoices covering index. Order matters — create
        // the new covering index FIRST so report queries always have
        // a valid plan; drop the now-redundant Phase-15 PERF-2 prefix
        // index SECOND.
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_landlord_status_due_covering_idx')) {
                $table->index(
                    ['landlord_id', 'status', 'due_date', 'total_due', 'amount_paid'],
                    'invoices_landlord_status_due_covering_idx',
                );
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasIndex('invoices', 'invoices_landlord_status_due_idx')) {
                $table->dropIndex('invoices_landlord_status_due_idx');
            }
        });

        // INDEX-7: expenses FK leading indexes. The (landlord_id,
        // category_id) composite is RETAINED — it serves "expenses
        // by category for landlord X". The new single-column indexes
        // serve "all expenses by category X" cross-landlord queries
        // + InnoDB lock-scope correctness for inserts.
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasIndex('expenses', 'expenses_category_id_idx')) {
                $table->index('category_id', 'expenses_category_id_idx');
            }
            if (! Schema::hasIndex('expenses', 'expenses_vendor_id_idx')) {
                $table->index('vendor_id', 'expenses_vendor_id_idx');
            }
            if (! Schema::hasIndex('expenses', 'expenses_property_date_idx')) {
                $table->index(['property_id', 'expense_date'], 'expenses_property_date_idx');
            }
            if (! Schema::hasIndex('expenses', 'expenses_building_date_idx')) {
                $table->index(['building_id', 'expense_date'], 'expenses_building_date_idx');
            }
            if (! Schema::hasIndex('expenses', 'expenses_unit_date_idx')) {
                $table->index(['unit_id', 'expense_date'], 'expenses_unit_date_idx');
            }
        });
    }

    public function down(): void
    {
        // Reverse order: rollback INDEX-7 last (it was created last).
        Schema::table('expenses', function (Blueprint $table) {
            foreach ([
                'expenses_unit_date_idx',
                'expenses_building_date_idx',
                'expenses_property_date_idx',
                'expenses_vendor_id_idx',
                'expenses_category_id_idx',
            ] as $idx) {
                if (Schema::hasIndex('expenses', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });

        // Restore Phase-15 PERF-2 prefix before dropping the covering
        // version, so report queries never lose a viable plan.
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_landlord_status_due_idx')) {
                $table->index(['landlord_id', 'status', 'due_date'], 'invoices_landlord_status_due_idx');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasIndex('invoices', 'invoices_landlord_status_due_covering_idx')) {
                $table->dropIndex('invoices_landlord_status_due_covering_idx');
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasIndex('wallet_transactions', 'wallet_transactions_landlord_created_idx')) {
                $table->dropIndex('wallet_transactions_landlord_created_idx');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasIndex('properties', 'properties_landlord_created_idx')) {
                $table->dropIndex('properties_landlord_created_idx');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasIndex('units', 'units_landlord_id_idx')) {
                $table->dropIndex('units_landlord_id_idx');
            }
        });

        Schema::table('buildings', function (Blueprint $table) {
            if (Schema::hasIndex('buildings', 'buildings_landlord_id_idx')) {
                $table->dropIndex('buildings_landlord_id_idx');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasIndex('invoice_items', 'invoice_items_invoice_id_idx')) {
                $table->dropIndex('invoice_items_invoice_id_idx');
            }
        });

        Schema::table('late_fees', function (Blueprint $table) {
            foreach ([
                'late_fees_active_invoice_fee_idx',
                'late_fees_landlord_active_date_idx',
            ] as $idx) {
                if (Schema::hasIndex('late_fees', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }
};
