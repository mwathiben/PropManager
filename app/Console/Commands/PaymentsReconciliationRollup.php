<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReconciliationReport;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-85 RECON-STRIPE-2: weekly per-landlord, per-gateway gauge of outstanding
 * reconciliation discrepancies (latest report per landlord+provider). Visibility-
 * only — the sev3 gateway_drift alert already fires from the daily run.
 */
class PaymentsReconciliationRollup extends Command
{
    protected $signature = 'payments:reconciliation-rollup';

    protected $description = 'Phase-85 RECON-STRIPE-2: emit landlord_gateway_discrepancies gauge.';

    public function handle(MetricsService $metrics): int
    {
        // Latest report id per (landlord_id, provider).
        $latestIds = ReconciliationReport::query()
            ->withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('landlord_id', 'provider')
            ->pluck('id');

        $rows = ReconciliationReport::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $latestIds)
            ->where('discrepancy_count', '>', 0)
            ->get(['landlord_id', 'provider', 'discrepancy_count']);

        $emitted = 0;
        foreach ($rows as $row) {
            try {
                $metrics->gauge(
                    'landlord_gateway_discrepancies',
                    (float) $row->discrepancy_count,
                    ['landlord_id' => (string) $row->landlord_id, 'gateway' => (string) $row->provider],
                );
                $emitted++;
            } catch (\Throwable $e) {
                Log::warning('payments:reconciliation-rollup gauge emit failed', [
                    'landlord_id' => $row->landlord_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("payments:reconciliation-rollup: {$emitted} gauge(s) emitted");

        return self::SUCCESS;
    }
}
