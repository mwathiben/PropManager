<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalIncident;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-32 SRE-INCIDENT-3: emit MTTR (mean time to resolve) p50/p90 by
 * severity over the last 90 days. The canonical SRE leadership metric.
 */
class MttrAudit extends Command
{
    protected $signature = 'mttr:audit {--days=90}';

    protected $description = 'Phase-32 SRE-INCIDENT-3: per-severity MTTR p50/p90 gauges.';

    public function handle(MetricsService $metrics): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));

        foreach (OperationalIncident::SEVERITIES as $severity) {
            $durations = OperationalIncident::query()
                ->where('severity', $severity)
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', $cutoff)
                ->get(['opened_at', 'resolved_at'])
                ->map(fn ($r) => (float) abs($r->opened_at->diffInMinutes($r->resolved_at)))
                ->values()
                ->all();

            $p50 = $this->percentile($durations, 0.5);
            $p90 = $this->percentile($durations, 0.9);
            $count = count($durations);

            $metrics->gauge('operational_incident_mttr_p50_minutes', $p50, ['severity' => $severity]);
            $metrics->gauge('operational_incident_mttr_p90_minutes', $p90, ['severity' => $severity]);

            $this->line(sprintf('severity=%s count=%d p50=%s p90=%s', $severity, $count, $p50, $p90));
        }

        return self::SUCCESS;
    }

    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $idx = (int) floor($p * (count($values) - 1));

        return (float) $values[$idx];
    }
}
