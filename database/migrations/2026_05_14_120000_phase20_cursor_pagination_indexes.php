<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-20 FRONT-UX-1 (closes Phase-19 INDEX-6): supporting indexes
 * for cursor pagination on the unbounded log tables.
 *
 * Cursor pagination orders by (created_at DESC, id DESC) and seeks
 * via WHERE (created_at, id) < (cursor_created_at, cursor_id). The
 * (landlord_id, created_at, id) composite lets MySQL do the seek
 * entirely in the index without a heap fetch.
 *
 * audit_logs: already has (landlord_id, created_at) from Phase-13.
 *   Adding the trailing `id` column extends it to support the
 *   cursor seek pattern.
 *
 * tenant_activities: has (landlord_id, tenant_id) + (tenant_id,
 *   created_at) but not (landlord_id, created_at, id). The cursor
 *   path needs landlord_id leading + created_at + id trailing.
 *
 * Both indexes are InnoDB-online (ALGORITHM=INPLACE LOCK=NONE).
 * Idempotent via Schema::hasIndex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('audit_logs', 'audit_logs_landlord_created_id_idx')) {
                $table->index(['landlord_id', 'created_at', 'id'], 'audit_logs_landlord_created_id_idx');
            }
        });

        Schema::table('tenant_activities', function (Blueprint $table) {
            if (! Schema::hasIndex('tenant_activities', 'tenant_activities_landlord_created_id_idx')) {
                $table->index(['landlord_id', 'created_at', 'id'], 'tenant_activities_landlord_created_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_activities', function (Blueprint $table) {
            if (Schema::hasIndex('tenant_activities', 'tenant_activities_landlord_created_id_idx')) {
                $table->dropIndex('tenant_activities_landlord_created_id_idx');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasIndex('audit_logs', 'audit_logs_landlord_created_id_idx')) {
                $table->dropIndex('audit_logs_landlord_created_id_idx');
            }
        });
    }
};
