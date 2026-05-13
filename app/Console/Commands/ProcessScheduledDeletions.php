<?php

namespace App\Console\Commands;

use App\Models\DeletionRequest;
use App\Services\DataDeletionService;
use Illuminate\Console\Command;

class ProcessScheduledDeletions extends Command
{
    /**
     * Phase-19 POLICY-9: guard rails on a destructive GDPR Article 17 cron.
     *   --max-deletions=N — safety cap; abort if the pending queue is
     *                       larger than N (default 50). Prevents a
     *                       runaway accidental mass-deletion.
     *   --dry-run         — count pending deletions, mutate nothing.
     *   --confirm         — required for interactive runs (scheduler
     *                       uses --no-interaction).
     */
    protected $signature = 'gdpr:process-deletions {--max-deletions=50} {--dry-run} {--confirm}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled account deletions (GDPR Article 17)';

    /**
     * Execute the console command.
     */
    public function handle(DataDeletionService $deletionService): int
    {
        $maxDeletions = (int) $this->option('max-deletions');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('confirm') && $this->input->isInteractive()) {
            $this->error('Refusing to run without --confirm in interactive mode (POLICY-9). Add --dry-run to preview.');

            return Command::FAILURE;
        }

        // POLICY-9: pre-flight count + cap. A runaway pending queue
        // (e.g. someone bulk-flagged accounts in error) hits the cap
        // and exits FAILURE rather than mass-deleting silently. Raise
        // the cap with --max-deletions=N when the volume is expected.
        $pendingCount = DeletionRequest::where('status', 'pending')
            ->where('scheduled_deletion_at', '<=', now())
            ->count();

        if ($pendingCount > $maxDeletions) {
            $this->error("Refusing to process {$pendingCount} deletions — exceeds --max-deletions={$maxDeletions} (POLICY-9). Investigate the queue then re-run with a higher cap.");

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] would process {$pendingCount} deletion request(s) — no DB writes.");

            return Command::SUCCESS;
        }

        $this->info("Processing {$pendingCount} scheduled deletion(s) (cap {$maxDeletions})...");

        $processed = $deletionService->processScheduledDeletions();

        $this->info("Processed {$processed} deletion request(s).");

        return Command::SUCCESS;
    }
}
