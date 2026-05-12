<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-15 PERF-7: notifications composite index on (recipient_id,
 * read_at) for the unread-count badge query. The badge fires on
 * every Inertia request that includes the layout — without this
 * index, MySQL scans every notification for the user and evaluates
 * the read_at NULL predicate per row.
 *
 * Phase-15 PERF-1 audit-time finding (payments composite on
 * landlord_id+payment_date) turned out to be already shipped by
 * the 2026_01_10 add_unique_constraint_to_paystack_reference
 * migration. Same for PERF-2 (invoices composite on
 * landlord_id+status+due_date) — already shipped by 2026_01_15
 * add_finance_hub_indexes. The payments table doesn't have a
 * 'status' column today (status semantics live on is_voided +
 * payment_method), so the original PERF-1 secondary index
 * (landlord_id, status) is moot.
 *
 * Both PERF-1 + PERF-2 closed-by-already-shipped; PERF-7 moves up
 * from Phase 3 to fill this slot.
 *
 * Idempotent via Schema::hasIndex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasIndex('notifications', 'notifications_recipient_read_idx')) {
                $table->index(['recipient_id', 'read_at'], 'notifications_recipient_read_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasIndex('notifications', 'notifications_recipient_read_idx')) {
                $table->dropIndex('notifications_recipient_read_idx');
            }
        });
    }
};
