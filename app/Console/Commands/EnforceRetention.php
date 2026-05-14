<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Phase-21 DEFER-DPA-3 (closes Phase-12 RETAIN-5 deferral):
 * Unified Kenya DPA / GDPR retention orchestrator. Pre-Phase-21 the
 * 6 retention commands ran on independent schedules — a failure of
 * one (e.g. logs:prune --table=audit silently no-op'd) wouldn't
 * surface as 'retention pipeline broken' until quarterly audit.
 *
 * This orchestrator runs the child commands in dependency order +
 * captures each exit code + emits a single
 * retention_pipeline_health{stage=X} Prometheus gauge per stage so
 * Phase-14 ops dashboards alert on pipeline failures.
 *
 * Dependency order (chosen so that earlier stages clean up rows
 * referenced by later stages):
 *   1. logs:prune (multiple log tables) — independent
 *   2. soft-deleted:purge — purges already-soft-deleted rows
 *      whose grace window has elapsed; runs before
 *      gdpr:process-deletions so that operator-initiated soft-deletes
 *      get finalized before user-initiated deletions are processed
 *   3. queue:prune-batches + queue:prune-failed — independent
 *   4. gdpr:process-deletions — user-initiated deletion finalisation
 *      (Phase-13 DPA workflow)
 *
 * exports:cleanup is a closure-based schedule entry (not a registered
 * command); the orchestrator does not call it. Individual schedule
 * entries continue to run — defensive duplication, idempotent
 * commands.
 *
 * Schedule entry: routes/console.php at 02:00 Africa/Nairobi,
 * withoutOverlapping(120), onOneServer.
 */
class EnforceRetention extends Command
{
    protected $signature = 'dpa:enforce-retention '
        .'{--dry-run : skip mutations, log only}';

    protected $description = 'Phase-21 DEFER-DPA-3: orchestrate the retention pipeline (logs/soft-delete/queue/gdpr) with per-stage health metrics.';

    /**
     * Ordered list of stages. `dry_run_supported` indicates whether the
     * child command accepts a --dry-run flag; only those receive it
     * when the orchestrator is invoked with --dry-run. Stages that
     * don't support dry-run are SKIPPED rather than failed when the
     * orchestrator is in dry-run mode (operator preview semantics).
     */
    private const PIPELINE = [
        ['stage' => 'logs_audit', 'command' => 'logs:prune', 'options' => ['--table' => 'audit', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'logs_security', 'command' => 'logs:prune', 'options' => ['--table' => 'security', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'logs_webhook', 'command' => 'logs:prune', 'options' => ['--table' => 'webhook', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'logs_bank_webhook', 'command' => 'logs:prune', 'options' => ['--table' => 'bank-webhook', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'logs_dead_letter', 'command' => 'logs:prune', 'options' => ['--table' => 'dead-letter', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'logs_consent', 'command' => 'logs:prune', 'options' => ['--table' => 'consent', '--confirm' => true], 'dry_run_supported' => true],
        ['stage' => 'soft_deleted_purge', 'command' => 'soft-deleted:purge', 'options' => ['--confirm' => true], 'dry_run_supported' => false],
        ['stage' => 'queue_prune_batches', 'command' => 'queue:prune-batches', 'options' => ['--hours' => 720], 'dry_run_supported' => false],
        ['stage' => 'queue_prune_failed', 'command' => 'queue:prune-failed', 'options' => ['--hours' => 720], 'dry_run_supported' => false],
        ['stage' => 'gdpr_process_deletions', 'command' => 'gdpr:process-deletions', 'options' => ['--confirm' => true], 'dry_run_supported' => true],
    ];

    public function handle(MetricsService $metrics): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $failureCount = 0;
        $skippedCount = 0;
        $stageResults = [];

        foreach (self::PIPELINE as $entry) {
            $stage = $entry['stage'];
            $command = $entry['command'];
            $supportsDryRun = $entry['dry_run_supported'] ?? false;

            if ($dryRun && ! $supportsDryRun) {
                $this->info(sprintf('[%s] %s — SKIPPED (no --dry-run support)', $stage, $command));
                $skippedCount++;
                $stageResults[$stage] = 'skipped';

                try {
                    $metrics->gauge('retention_pipeline_health', 1.0, ['stage' => $stage]);
                } catch (\Throwable) {
                }

                continue;
            }

            $options = $dryRun && $supportsDryRun
                ? array_merge($entry['options'], ['--dry-run' => true])
                : $entry['options'];

            $this->info(sprintf('[%s] %s', $stage, $command));

            try {
                $exitCode = Artisan::call($command, $options);
                $stageResults[$stage] = $exitCode;

                $output = trim(Artisan::output());
                if ($output !== '') {
                    foreach (explode("\n", $output) as $line) {
                        $this->line("  $line");
                    }
                }

                try {
                    $metrics->gauge(
                        'retention_pipeline_health',
                        (float) ($exitCode === 0 ? 1 : 0),
                        ['stage' => $stage],
                    );
                } catch (\Throwable) {
                }

                if ($exitCode !== 0) {
                    $failureCount++;
                    $this->warn("  Stage $stage exited with code $exitCode");
                }
            } catch (\Throwable $e) {
                $failureCount++;
                $stageResults[$stage] = -1;

                try {
                    $metrics->gauge(
                        'retention_pipeline_health',
                        0.0,
                        ['stage' => $stage],
                    );
                } catch (\Throwable) {
                }

                $this->error("  Stage $stage threw: ".$e->getMessage());
                Log::channel(config('logging.schedule_channel', 'stack'))->error(
                    'dpa:enforce-retention stage exception',
                    ['stage' => $stage, 'command' => $command, 'exception' => $e->getMessage()],
                );
            }
        }

        $totalStages = count(self::PIPELINE);
        $successCount = $totalStages - $failureCount;
        $this->info("dpa:enforce-retention: $successCount/$totalStages stages succeeded.");

        try {
            $metrics->gauge('retention_pipeline_failure_count', (float) $failureCount);
        } catch (\Throwable) {
        }

        if ($failureCount > 0) {
            Log::channel(config('logging.schedule_channel', 'stack'))->error(
                'dpa:enforce-retention pipeline reported failures',
                ['failures' => $failureCount, 'stage_results' => $stageResults],
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
