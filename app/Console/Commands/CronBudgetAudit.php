<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WorkflowRunLog;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-36 INSIGHT-CRON-2/3: daily cron runtime budget audit.
 *
 *   - Reads last 24h of workflow_runs_log where duration_ms IS NOT
 *     NULL (only rows written via WorkflowLogger::measure carry
 *     timing).
 *   - Emits cron_runtime_per_command_minutes_24h{command=X} gauge
 *     for top 30 by descending total + cron_runtime_total_minutes_
 *     24h aggregate.
 *   - Fires high_cron_runtime (sev3) when daily total exceeds the
 *     configured threshold (default 60 minutes).
 */
class CronBudgetAudit extends Command
{
    protected $signature = 'cron:budget-audit {--threshold=60}';

    protected $description = 'Phase-36 INSIGHT-CRON-2/3: per-command + total cron runtime gauge + budget alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $thresholdMinutes = max(1, (int) $this->option('threshold'));

        $since = now()->subDay();
        $rows = WorkflowRunLog::query()
            ->whereNotNull('duration_ms')
            ->where('fired_at', '>=', $since)
            ->selectRaw('workflow_name, SUM(duration_ms) as total_ms')
            ->groupBy('workflow_name')
            ->orderByDesc('total_ms')
            ->get();

        $totalMs = 0;
        foreach ($rows->take(30) as $row) {
            $minutes = round(((int) $row->total_ms) / 1000.0 / 60.0, 4);
            $metrics->gauge(
                'cron_runtime_per_command_minutes_24h',
                $minutes,
                ['command' => $row->workflow_name],
            );
            $totalMs += (int) $row->total_ms;
        }

        // Sum across ALL rows (not just top 30) for the aggregate.
        $totalMs = (int) WorkflowRunLog::query()
            ->whereNotNull('duration_ms')
            ->where('fired_at', '>=', $since)
            ->sum('duration_ms');
        $totalMinutes = round($totalMs / 1000.0 / 60.0, 4);
        $metrics->gauge('cron_runtime_total_minutes_24h', $totalMinutes);

        $this->line(sprintf(
            'Tracked %d command(s). Total 24h runtime: %.2f minutes (threshold %d).',
            $rows->count(),
            $totalMinutes,
            $thresholdMinutes,
        ));

        if ($totalMinutes > $thresholdMinutes) {
            $recorder->record(
                alertKey: 'high_cron_runtime',
                value: $totalMinutes,
                threshold: (float) $thresholdMinutes,
                metadata: ['per_command_minutes' => $rows->take(10)->map(fn ($r) => [
                    'command' => $r->workflow_name,
                    'minutes' => round(((int) $r->total_ms) / 1000.0 / 60.0, 4),
                ])->all()],
            );
        } else {
            $recorder->resolve('high_cron_runtime');
        }

        return self::SUCCESS;
    }
}
