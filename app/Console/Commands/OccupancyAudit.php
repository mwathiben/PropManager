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

    public function handle(MetricsService $metrics): int
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
                if (! $dryRun) {
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

                if ($row['is_below_target']) {
                    $key = sprintf(
                        'occupancy-breach:%d:%s',
                        $row['building_id'],
                        CarbonImmutable::now()->format('Y-m'),
                    );
                    if (Cache::add($key, true, now()->addDays(30))) {
                        $breached++;
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
                    }
                }
            }
        }

        $this->info(sprintf(
            'occupancy:audit: %d building(s) measured, %d new breach event(s)%s',
            $totalRows,
            $breached,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
