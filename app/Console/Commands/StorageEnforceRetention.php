<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileRetentionPolicy;
use App\Services\Storage\FileRetentionService;
use Illuminate\Console\Command;

/**
 * Phase-59 FILE-RETENTION-2/3: walks every subject in
 * FileRetentionPolicy::SUBJECTS and runs FileRetentionService::enforce
 * per subject. Each subject runs in its own try/catch so one failure
 * doesn't block the others.
 *
 * Schedule: daily 02:30 Africa/Nairobi (avoids the dpa:enforce-retention
 * 02:00 collision).
 */
class StorageEnforceRetention extends Command
{
    protected $signature = 'storage:enforce-retention '
        .'{--dry-run : skip mutations, log per-subject candidates only}';

    protected $description = 'Phase-59 FILE-RETENTION: per-subject file purge cron.';

    public function handle(FileRetentionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalDeleted = 0;
        $totalErrors = 0;

        foreach (FileRetentionPolicy::SUBJECTS as $subject) {
            $result = $service->enforce($subject, $dryRun);
            $this->info("subject={$subject} deleted={$result['deleted']} errors={$result['errors']}");
            $totalDeleted += $result['deleted'];
            $totalErrors += $result['errors'];
        }

        $this->line("total: deleted={$totalDeleted} errors={$totalErrors} dry_run=".($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
