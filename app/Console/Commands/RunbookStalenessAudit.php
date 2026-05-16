<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\AlertRegistry;
use Illuminate\Console\Command;

/**
 * Phase-32 SRE-RUNBOOK-3: emit per-runbook staleness gauges. A
 * runbook that hasn't been touched in 180 days while its alert
 * fires monthly is suspicious — either the runbook is out of date
 * or the alert needs retiring. Either way an operator should look.
 */
class RunbookStalenessAudit extends Command
{
    protected $signature = 'runbook:staleness-audit {--threshold-days=180}';

    protected $description = 'Phase-32 SRE-RUNBOOK-3: bucket runbooks by days-since-mtime.';

    public function handle(AlertRegistry $registry, MetricsService $metrics): int
    {
        $threshold = max(1, (int) $this->option('threshold-days'));
        $now = time();
        $stale = 0;
        $reported = [];

        foreach ($registry->all() as $alert) {
            $ref = (string) ($alert['runbook'] ?? '');
            if ($ref === '') {
                continue;
            }
            $path = strpos($ref, '#') !== false ? substr($ref, 0, strpos($ref, '#')) : $ref;
            $abs = base_path($path);
            if (! file_exists($abs)) {
                continue;
            }
            if (isset($reported[$path])) {
                continue;
            }
            $reported[$path] = true;
            $mtime = (int) filemtime($abs);
            $days = (int) floor(($now - $mtime) / 86_400);
            $metrics->gauge('runbook_staleness_days', (float) $days, ['runbook' => $path]);
            if ($days > $threshold) {
                $stale++;
                $this->warn(sprintf('STALE %d days: %s', $days, $path));
            }
        }

        $metrics->gauge('runbook_stale_count', (float) $stale);
        $this->info(sprintf('Audited %d runbook(s), %d stale (> %d days).', count($reported), $stale, $threshold));

        return self::SUCCESS;
    }
}
