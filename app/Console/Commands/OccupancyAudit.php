<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\OccupancyTargetBreached;
use App\Models\Building;
use App\Models\User;
use App\Services\MetricsService;
use App\Services\OccupancyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-29 WF-VACANCY-1/3: nightly per-property + per-building
 * occupancy aggregation. Emits Prometheus gauges
 * occupancy_rate{building_id} + vacancy_count{building_id} for every
 * building (including 0-vacancy ones so the time series stays
 * continuous). Fires OccupancyTargetBreached for buildings with a
 * configured target_occupancy_rate that the current rate drops below
 * — idempotent per building per year-month via Cache::add (30d lock).
 */
class OccupancyAudit extends Command
{
    protected $signature = 'occupancy:audit {--dry-run} {--landlord-id=}';

    protected $description = 'Phase-29 WF-VACANCY-1/3: emit occupancy gauges + breach events.';

    public function __construct(
        private readonly OccupancyService $occupancyService,
    ) {
        parent::__construct();
    }

    public function handle(MetricsService $metrics, \App\Services\WorkflowLogger $workflowLogger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scopedLandlordId = $this->option('landlord-id');

        $landlords = User::query()
            ->where('role', 'landlord')
            ->when($scopedLandlordId, fn ($q) => $q->where('id', $scopedLandlordId))
            ->get();

        $totalRows = 0;
        $breached = 0;

        foreach ($landlords as $landlord) {
            $rows = $this->occupancyService->byBuilding($landlord);

            foreach ($rows as $row) {
                $totalRows++;
                $this->emitMetrics($metrics, $row, $dryRun);
                $breached += $this->handleBreachDetection($row, $dryRun);
            }
        }

        $this->info(sprintf(
            'occupancy:audit: %d building(s) measured, %d new breach event(s)%s',
            $totalRows,
            $breached,
            $dryRun ? ' (dry-run)' : '',
        ));

        $workflowLogger->log(
            workflowName: 'occupancy:audit',
            action: 'completed',
            metadata: ['rows' => $totalRows, 'breached' => $breached, 'dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }

    private function emitMetrics(MetricsService $metrics, array $row, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        try {
            $metrics->gauge('occupancy_rate', $row['occupancy_rate_pct'], [
                'building_id' => (string) $row['building_id'],
            ]);
            $metrics->gauge('vacancy_count', (float) $row['vacant_units'], [
                'building_id' => (string) $row['building_id'],
            ]);
        } catch (\Throwable) {
            // Metrics emit is best-effort.
        }
    }

    private function handleBreachDetection(array $row, bool $dryRun): int
    {
        if (! $row['is_below_target']) {
            return 0;
        }

        $key = sprintf(
            'occupancy-breach:%d:%s',
            $row['building_id'],
            CarbonImmutable::now()->format('Y-m'),
        );

        if (! Cache::add($key, true, now()->addDays(30))) {
            return 0;
        }

        if (! $dryRun) {
            $building = Building::query()
                ->withoutGlobalScope('landlord')
                ->find($row['building_id']);
            if ($building) {
                OccupancyTargetBreached::dispatch(
                    $building,
                    $row['occupancy_rate_pct'],
                    (float) $row['target_occupancy_rate'],
                    CarbonImmutable::now(),
                );
            }
        }

        return 1;
    }
}
