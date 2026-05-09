<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 audit PERF-R5/R6/R7: composite indexes that turn the hottest
 * landlord-scoped queries into seek+range lookups.
 *
 * R5 — payments(landlord_id, payment_date): 12+ call sites use
 *      whereMonth('payment_date', ...) which can't use a function index.
 *      A composite (landlord_id, payment_date) lets MySQL filter by
 *      landlord then range-scan the date bucket.
 * R6 — notifications(landlord_id, created_at): hub/index pages paginate
 *      by created_at desc; without this composite, MySQL filesorts after
 *      filtering by landlord_id.
 * R7 — users(landlord_id, role): 25+ call sites filter caretakers/tenants
 *      by landlord. SQLite has no auto-index on FK columns, so test runs
 *      were slower; this normalizes performance across MySQL/SQLite envs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasIndex('payments', 'payments_landlord_date_idx')) {
                $table->index(['landlord_id', 'payment_date'], 'payments_landlord_date_idx');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasIndex('notifications', 'notifications_landlord_created_idx')) {
                $table->index(['landlord_id', 'created_at'], 'notifications_landlord_created_idx');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasIndex('users', 'users_landlord_role_idx')) {
                $table->index(['landlord_id', 'role'], 'users_landlord_role_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasIndex('payments', 'payments_landlord_date_idx')) {
                $table->dropIndex('payments_landlord_date_idx');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasIndex('notifications', 'notifications_landlord_created_idx')) {
                $table->dropIndex('notifications_landlord_created_idx');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_landlord_role_idx')) {
                $table->dropIndex('users_landlord_role_idx');
            }
        });
    }
};
