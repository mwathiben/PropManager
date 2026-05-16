<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\LeaseRenewalApproaching;
use App\Models\Lease;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-29 WF-LEASE-RENEW-1: nightly scan for leases approaching
 * end_date at exactly 60/30/7 days remaining. Fires
 * LeaseRenewalApproaching event for each match — listener handles
 * notification fan-out under Phase-16 RESIL backoff.
 *
 * Idempotency: Cache::add keyed on lease_id + bucket + year-month,
 * lock 60d. Prevents same-day re-runs from double-firing AND lets
 * next year's renewal fire correctly when month rolls over.
 */
class LeasesScanRenewals extends Command
{
    protected $signature = 'leases:scan-renewals {--dry-run}';

    protected $description = 'Phase-29 WF-LEASE-RENEW-1: emit lease renewal approaching events at T-60/30/7 days.';

    /** @var int[] */
    public const BUCKETS = [60, 30, 7];

    public function handle(\App\Services\WorkflowLogger $workflowLogger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = CarbonImmutable::now()->startOfDay();
        $fired = 0;

        $leases = Lease::query()
            ->withoutGlobalScope('landlord')
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays(max(self::BUCKETS) + 1)->toDateString()])
            ->get();

        foreach ($leases as $lease) {
            $endDate = CarbonImmutable::parse($lease->end_date)->startOfDay();
            $daysRemaining = (int) $today->diffInDays($endDate, false);

            if (! in_array($daysRemaining, self::BUCKETS, true)) {
                continue;
            }

            $key = sprintf(
                'lease-renewal:%d:%d:%s',
                $lease->id,
                $daysRemaining,
                $today->format('Y-m'),
            );
            if (! Cache::add($key, true, now()->addDays(60))) {
                continue;
            }

            if ($dryRun) {
                $fired++;

                continue;
            }

            LeaseRenewalApproaching::dispatch($lease, $daysRemaining, CarbonImmutable::now());
            $fired++;
        }

        $this->info("leases:scan-renewals: {$fired} renewal event(s) fired".($dryRun ? ' (dry-run)' : ''));

        $workflowLogger->log(
            workflowName: 'leases:scan-renewals',
            action: 'completed',
            metadata: ['fired' => $fired, 'dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }
}
