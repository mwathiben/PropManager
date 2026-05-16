<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\UsageRecord;

/**
 * Phase-35 PLATFORM-METER-1: thin wrapper around UsageRecord::
 * incrementUsage with fail-open semantics.
 *
 * UsageRecord (committed 2025-12-27) is the canonical per-period
 * counter; this service makes calls cheap + safe to bolt into hot
 * paths (middleware terminate, controller actions) without
 * worrying that a metering failure tears down the request.
 *
 * Idempotent within a request — bulk-call patterns coalesce via
 * the underlying SQL `quantity + N` raw update.
 */
class MeteredUsageRecorder
{
    public function record(int $userId, string $feature, int $delta = 1): void
    {
        if ($delta <= 0) {
            return;
        }

        try {
            UsageRecord::incrementUsage($userId, $feature, $delta);
        } catch (\Throwable) {
            // Fail-open: metering MUST NEVER block the request.
        }
    }
}
