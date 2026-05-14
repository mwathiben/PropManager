<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-21 DEFER-OBSERV-1 (closes Phase-14 OBSERV-10 deferral):
 * Phase 14 wired request_id middleware (AddRequestId) + queue boundary
 * propagation (CarriesRequestId/PropagatesRequestId) + logs:correlate
 * command, but the webhook tables didn't carry request_id — so a
 * webhook event couldn't be tied back to the request that ingested it.
 *
 * This migration adds nullable string(36) request_id columns to
 * webhook_logs + bank_webhook_logs with an index for the correlate
 * query path. WebhookLogService::recordHit + BankWebhookController
 * stamp the column on insert.
 *
 * Append-only column (nullable end-of-table); no shape change to
 * existing rows. Index supports logs:correlate --request-id=X.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('webhook_logs', 'request_id')) {
                $table->string('request_id', 36)->nullable()->after('ip_address');
                $table->index('request_id', 'webhook_logs_request_id_idx');
            }
        });

        Schema::table('bank_webhook_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_webhook_logs', 'request_id')) {
                $table->string('request_id', 36)->nullable();
                $table->index('request_id', 'bank_webhook_logs_request_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_logs', 'request_id')) {
                $table->dropIndex('webhook_logs_request_id_idx');
                $table->dropColumn('request_id');
            }
        });

        Schema::table('bank_webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('bank_webhook_logs', 'request_id')) {
                $table->dropIndex('bank_webhook_logs_request_id_idx');
                $table->dropColumn('request_id');
            }
        });
    }
};
