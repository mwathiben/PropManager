<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WorkflowRunLog;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-29 WF-CI-2: nightly silent-failure detector. For each known
 * Phase-29 workflow, counts last-24h rows in workflow_runs_log and
 * alerts via the schedule log channel when an expected workflow shows
 * zero rows. Also emits workflow_runs_total{workflow} gauge for
 * Grafana dashboards.
 *
 * "Expected" is a static list because each scheduler should fire at
 * LEAST once per 24h in the absence of any qualifying data
 * (e.g., occupancy:audit should write a row per building, even when
 * none breach). Commands that only fire on real activity (rent
 * reminders, late-fee escalation, lease renewal scan) are tracked but
 * NOT alerted on — zero is a legitimate state for an empty corpus.
 */
class WorkflowHealth extends Command
{
    protected $signature = 'workflow:health';

    protected $description = 'Phase-29 WF-CI-2: detect silent failures in Phase-29 workflow schedulers.';

    /**
     * Workflows that are expected to write at least one row per 24h.
     *
     * @var string[]
     */
    public const EXPECTED_DAILY = [
        'WF-VACANCY-1', // occupancy:audit writes one row per landlord
    ];

    /**
     * Workflows tracked for observability but not alerted on zero —
     * zero is a legitimate state (no overdue invoices, no leases
     * approaching renewal, no rent reminders due).
     *
     * @var string[]
     */
    public const TRACKED_ONLY = [
        'WF-RENT-REMIND-1',
        'WF-LEASE-RENEW-1',
        'WF-LATE-FEE-1',
        'WF-VACANCY-2',
        'WF-VACANCY-3',
        'WF-PAY-APPROVE-1',
        'WF-PAY-APPROVE-2',
        'WF-PAY-APPROVE-3',
    ];

    public function handle(MetricsService $metrics): int
    {
        $silentCount = 0;

        foreach ([...self::EXPECTED_DAILY, ...self::TRACKED_ONLY] as $workflowName) {
            $count = WorkflowRunLog::query()
                ->forWorkflow($workflowName)
                ->inLast24h()
                ->count();

            try {
                $metrics->gauge('workflow_runs_total', (float) $count, [
                    'workflow' => $workflowName,
                ]);
            } catch (\Throwable) {
                // Metrics emit is best-effort.
            }

            if (in_array($workflowName, self::EXPECTED_DAILY, true) && $count === 0) {
                $silentCount++;
                Log::channel(config('logging.schedule_channel', 'stack'))->warning(
                    "workflow:health silent failure: {$workflowName} produced 0 rows in last 24h",
                    ['workflow' => $workflowName],
                );
            }
        }

        $this->info("workflow:health: {$silentCount} silent workflow(s) detected");

        return self::SUCCESS;
    }
}
