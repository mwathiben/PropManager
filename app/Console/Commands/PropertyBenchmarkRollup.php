<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\MetricsService;
use App\Services\Property\PropertyMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-78 PROPERTY-BENCHMARK: weekly per-landlord portfolio occupancy gauge for
 * ops dashboards. Visibility-only — no alert (mirrors Phase-49
 * maintenance:cost-rollup: gauge + dashboard, no paging).
 *
 * Cron: weekly Sunday 05:05 Africa/Nairobi (immediately after the Phase-49
 * maintenance:cost-rollup at 05:00 — same Sunday cost/rollup cluster).
 */
class PropertyBenchmarkRollup extends Command
{
    protected $signature = 'property:benchmark-rollup';

    protected $description = 'Phase-78 PROPERTY-BENCHMARK: emit landlord_portfolio_occupancy_pct gauge.';

    public function handle(MetricsService $metrics, PropertyMetricsService $propertyMetrics): int
    {
        $landlordIds = Property::query()
            ->select('landlord_id')
            ->distinct()
            ->pluck('landlord_id');

        $emitted = 0;
        foreach ($landlordIds as $landlordId) {
            $rows = $propertyMetrics->forLandlord((int) $landlordId);
            $totalUnits = array_sum(array_column($rows, 'unit_count'));
            if ($totalUnits === 0) {
                continue;
            }

            $occupied = array_sum(array_column($rows, 'occupied_count'));
            $occupancy = round($occupied / $totalUnits * 100, 1);

            try {
                $metrics->gauge(
                    'landlord_portfolio_occupancy_pct',
                    (float) $occupancy,
                    ['landlord_id' => (string) $landlordId],
                );
                $emitted++;
            } catch (\Throwable $e) {
                Log::warning('property:benchmark-rollup gauge emit failed', [
                    'landlord_id' => $landlordId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("property:benchmark-rollup: {$emitted} landlord gauge(s) emitted");

        return self::SUCCESS;
    }
}
