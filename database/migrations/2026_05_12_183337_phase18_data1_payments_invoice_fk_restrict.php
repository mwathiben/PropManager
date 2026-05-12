<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-18 DATA-1: flip payments.invoice_id ON DELETE behaviour from
 * CASCADE to RESTRICT.
 *
 * Pre-fix: force-deleting an Invoice (Invoice uses SoftDeletes so this
 * required an explicit force-delete) would CASCADE-nuke every Payment
 * row that referenced it. Phase-13 DPA-3 sets Payment.lawful_basis to
 * legal_obligation with 7-year retention — silent cascade violated
 * the Kenya DPA Article 30 retention obligation.
 *
 * Post-fix: an attempt to force-delete an Invoice with attached
 * Payments raises a foreign-key-constraint exception. The error is
 * the FEATURE — surfacing the policy violation explicitly instead of
 * silently violating retention.
 *
 * Operator action: if a misposted Invoice needs force-deletion, the
 * Payments must be archived (Phase-12 RETAIN-3 archive flow) first.
 * Soft-deleting the Invoice continues to work unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();
        });
    }
};
