<?php

declare(strict_types=1);

namespace App\Services\Cost;

use App\Models\LandlordUsageMetric;
use Illuminate\Support\Facades\DB;

/**
 * Phase-33 COST-ATTRIB-1: race-safe per-landlord usage counter.
 *
 * Atomic UPSERT using INSERT ... ON DUPLICATE KEY UPDATE so two
 * concurrent requests for the same (landlord, metric, day) merge
 * into a single growing total instead of one clobbering the other.
 *
 * Fail-open: any DB error is silently swallowed — recording usage
 * must NEVER affect the user-facing request path. The Phase-22
 * MetricsService::gauge already demonstrates this discipline.
 */
class LandlordUsageMetricRecorder
{
    public function add(int $landlordId, string $metric, int $delta, ?\DateTimeInterface $day = null): void
    {
        if ($delta <= 0) {
            return;
        }
        if (! in_array($metric, LandlordUsageMetric::METRICS, true)) {
            throw new \InvalidArgumentException("Unknown landlord usage metric: {$metric}");
        }

        $dayStr = ($day ?? now())->format('Y-m-d');

        try {
            DB::statement(
                'INSERT INTO landlord_usage_metrics (landlord_id, metric, day, value, created_at, updated_at)'
                .' VALUES (?, ?, ?, ?, NOW(), NOW())'
                .' ON DUPLICATE KEY UPDATE value = value + VALUES(value), updated_at = NOW()',
                [$landlordId, $metric, $dayStr, $delta],
            );
        } catch (\Throwable) {
            // Fail-open: cost attribution is operational telemetry,
            // never block a real request because the metric table is
            // unavailable.
        }
    }
}
