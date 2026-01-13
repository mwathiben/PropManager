<?php

namespace App\Console\Commands;

use App\Services\DataDeletionService;
use Illuminate\Console\Command;

class ProcessScheduledDeletions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdpr:process-deletions';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled account deletions (GDPR Article 17)';

    /**
     * Execute the console command.
     */
    public function handle(DataDeletionService $deletionService): int
    {
        $this->info('Processing scheduled deletions...');

        $processed = $deletionService->processScheduledDeletions();

        $this->info("Processed {$processed} deletion request(s).");

        return Command::SUCCESS;
    }
}
