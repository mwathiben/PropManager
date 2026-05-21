<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Maintenance\CaretakerPerformanceService;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-80 CARETAKER-PERF-3: weekly per-caretaker within-SLA gauge for ops
 * dashboards. Visibility-only — no alert (mirrors maintenance:cost-rollup /
 * property:benchmark-rollup). Caretakers with no resolved tickets in the window
 * (within_sla_pct null) are skipped.
 */
class CaretakerPerformanceRollup extends Command
{
    protected $signature = 'caretaker:performance-rollup';

    protected $description = 'Phase-80 CARETAKER-PERF: emit landlord_caretaker_within_sla_pct gauge.';

    public function handle(MetricsService $metrics, CaretakerPerformanceService $performance): int
    {
        $landlordIds = User::query()
            ->where('role', 'caretaker')
            ->whereNotNull('landlord_id')
            ->distinct()
            ->pluck('landlord_id');

        $emitted = 0;
        foreach ($landlordIds as $landlordId) {
            foreach ($performance->forLandlord((int) $landlordId, 90) as $row) {
                if ($row['within_sla_pct'] === null) {
                    continue;
                }

                try {
                    $metrics->gauge(
                        'landlord_caretaker_within_sla_pct',
                        (float) $row['within_sla_pct'],
                        ['landlord_id' => (string) $landlordId, 'caretaker_id' => (string) $row['caretaker_id']],
                    );
                    $emitted++;
                } catch (\Throwable $e) {
                    Log::warning('caretaker:performance-rollup gauge emit failed', [
                        'landlord_id' => $landlordId,
                        'caretaker_id' => $row['caretaker_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("caretaker:performance-rollup: {$emitted} caretaker gauge(s) emitted");

        return self::SUCCESS;
    }
}
