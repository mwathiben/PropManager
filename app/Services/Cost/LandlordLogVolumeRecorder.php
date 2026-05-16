<?php

declare(strict_types=1);

namespace App\Services\Cost;

use Illuminate\Support\Facades\DB;

/**
 * Phase-33 COST-LOGS-1: race-safe per-landlord log-volume counter.
 * Same INSERT...ON DUPLICATE KEY UPDATE pattern as
 * LandlordUsageMetricRecorder — atomic increment, no read-then-write
 * race.
 *
 * Fail-open: recorder errors are never propagated. A log-write that
 * fails to record its volume is far less harmful than a log handler
 * that throws and tears down the request.
 *
 * Callers are batched (cron flush of an in-memory buffer, NOT per
 * log line) so the table is not itself a hot path.
 */
class LandlordLogVolumeRecorder
{
    public function add(int $landlordId, int $byteCount, int $lineCount = 1, ?\DateTimeInterface $day = null): void
    {
        if ($byteCount <= 0 && $lineCount <= 0) {
            return;
        }

        $dayStr = ($day ?? now())->format('Y-m-d');

        try {
            DB::statement(
                'INSERT INTO log_volume_daily (landlord_id, day, byte_count, line_count, created_at, updated_at)'
                .' VALUES (?, ?, ?, ?, NOW(), NOW())'
                .' ON DUPLICATE KEY UPDATE'
                .' byte_count = byte_count + VALUES(byte_count),'
                .' line_count = line_count + VALUES(line_count),'
                .' updated_at = NOW()',
                [$landlordId, $dayStr, max(0, $byteCount), max(0, $lineCount)],
            );
        } catch (\Throwable) {
            // Fail-open: log volume tracking is observability, never
            // block a request because the counter table is unavailable.
        }
    }
}
