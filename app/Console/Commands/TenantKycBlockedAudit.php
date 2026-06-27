<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MetricsService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;

/**
 * Phase-53 GAUGE-WIRING-1: emit tenant_kyc_blocked_count Prometheus
 * gauge for tenants stuck at the Phase-48 wizard step-2 advance gate
 * (kycProgress.submitted < required). Hourly cadence at :15 past so
 * the gauge timeline is granular enough for the sev4 alert window
 * (alert-thresholds.md line 33, threshold 20/24h).
 *
 * The gauge is a point-in-time snapshot, not an accumulator, so a
 * tenant who unblocks themselves between runs drops out naturally.
 */
class TenantKycBlockedAudit extends Command
{
    protected $signature = 'tenant-kyc:blocked-audit {--dry-run}';

    protected $description = 'Phase-53 GAUGE-WIRING-1: emit tenant_kyc_blocked_count gauge.';

    public function handle(MetricsService $metrics, WorkflowLogger $workflowLogger): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $blocked = $this->countBlockedTenants();

        $this->emitGaugeUnlessDryRun($metrics, $blocked, $dryRun);

        $this->info(sprintf(
            'tenant-kyc:blocked-audit: %d tenant(s) blocked at step-2 gate%s',
            $blocked,
            $dryRun ? ' (dry-run)' : '',
        ));

        $workflowLogger->log(
            workflowName: 'tenant-kyc:blocked-audit',
            action: 'completed',
            metadata: ['blocked' => $blocked, 'dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }

    private function countBlockedTenants(): int
    {
        $blocked = 0;

        User::query()
            ->where('role', 'tenant')
            ->whereHas('lease', fn ($q) => $q->where('is_active', true))
            ->with(['lease.unit'])
            ->chunkById(200, function ($tenants) use (&$blocked): void {
                foreach ($tenants as $tenant) {
                    $progress = $tenant->kycProgress();
                    if ($progress['required'] > 0 && $progress['submitted'] < $progress['required']) {
                        $blocked++;
                    }
                }
            });

        return $blocked;
    }

    private function emitGaugeUnlessDryRun(MetricsService $metrics, int $blocked, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        try {
            $metrics->gauge('tenant_kyc_blocked_count', (float) $blocked);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
