<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lease\LeaseRenewalAutoService;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-61 RENEWAL-AUTO-2: scans leases within
 * config('lease.auto_renew_scan_days_ahead') of expiry and creates
 * the next-period lease for auto_renew=true rows. Runs daily 07:00
 * Africa/Nairobi (after lease-renewal:expire-stale-counters at
 * 03:00 so counter-proposals get resolved first).
 */
class LeaseAutoRenew extends Command
{
    protected $signature = 'lease:auto-renew '
        .'{--dry-run : log candidates without creating new leases}';

    protected $description = 'Phase-61 RENEWAL-AUTO-2: auto-create next-period leases for opted-in expiring leases.';

    public function handle(LeaseRenewalAutoService $service, MetricsService $metrics): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $daysAhead = (int) config('lease.auto_renew_scan_days_ahead', 30);

        $renewed = $service->scanExpiring($daysAhead, $dryRun);

        $count = count($renewed);
        $metrics->gauge('lease_auto_renewed_count', $count);
        $this->info('lease_auto_renew renewed='.$count.' dry_run='.($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
