<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 TICKETS-SLA-DEEP-1: resolution-stage SLA tracking.
 *
 * Phase 28 shipped sla_due_at for first-response only. Phase 49 adds
 * resolution_due_at so the cron can also detect tickets that were
 * acknowledged within 4h but never fixed within 24h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_at');
            $table->index('resolution_due_at', 'tickets_resolution_due_at_idx');
        });

        // Backfill OPEN/ACKNOWLEDGED/IN_PROGRESS tickets with a
        // resolution_due_at derived from created_at + per-priority offset.
        $resolutionSeconds = [
            'urgent' => 86400,        // 24h
            'high' => 604800,         // 7d
            'medium' => 1209600,      // 14d
            'low' => 2592000,         // 30d
        ];

        $openStatuses = ['open', 'acknowledged', 'in_progress'];

        foreach ($resolutionSeconds as $priority => $seconds) {
            DB::table('tickets')
                ->whereIn('status', $openStatuses)
                ->where('priority', $priority)
                ->whereNull('resolution_due_at')
                ->whereNotNull('created_at')
                ->update([
                    'resolution_due_at' => DB::raw("DATE_ADD(created_at, INTERVAL {$seconds} SECOND)"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_resolution_due_at_idx');
            $table->dropColumn('resolution_due_at');
        });
    }
};
