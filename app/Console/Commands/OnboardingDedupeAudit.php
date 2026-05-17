<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Onboarding\MirrorAuditService;
use Illuminate\Console\Command;

/**
 * Phase-46 CANONICAL-AUDIT-1/2: emit canonical_mirror_drift_count gauge
 * per registered mirror; pinned mirrors open sev3 at threshold > 0;
 * loose mirrors open sev4 at threshold 5/24h (via alert-thresholds.md row).
 *
 * Cron: daily 03:30 Africa/Nairobi (after 03:15 stripe-balance-audit).
 */
class OnboardingDedupeAudit extends Command
{
    protected $signature = 'onboarding:dedupe-audit {--verbose-rows : Log each drifted mirror row}';

    protected $description = 'Phase-46 CANONICAL-AUDIT-1: audit users.* mirror columns against canonical sources.';

    public function handle(MirrorAuditService $audit, MetricsService $metrics): int
    {
        $results = $audit->scan();
        $totalDrift = 0;

        foreach ($results as $row) {
            $metrics->gauge(
                'canonical_mirror_drift_count',
                $row['drift_count'],
                ['mirror' => $row['mirror']],
            );

            $totalDrift += $row['drift_count'];

            if ($row['drift_count'] > 0) {
                $severity = $row['pinned'] ? 'sev3' : 'sev4';
                $this->warn(sprintf(
                    '[%s] %s drifted from %s — %d row(s).',
                    $severity,
                    $row['mirror'],
                    $row['canonical'],
                    $row['drift_count'],
                ));
            }
        }

        $this->info(sprintf(
            'Audited %d mirror(s); total drifted rows: %d.',
            $results->count(),
            $totalDrift,
        ));

        return self::SUCCESS;
    }
}
