<?php

namespace App\Console\Commands;

use App\Services\SchedulerService;
use Illuminate\Console\Command;

class ProcessNotificationSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all active notification schedules';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $scheduler): int
    {
        $this->info('Processing notification schedules...');

        $results = $scheduler->processSchedules();

        $this->info("Processed: {$results['processed']} schedules");
        $this->info("Notifications sent: {$results['notifications_sent']}");

        if (! empty($results['errors'])) {
            $this->error('Errors:');
            foreach ($results['errors'] as $error) {
                $this->error("  Schedule #{$error['schedule_id']}: {$error['error']}");
            }
        }

        return self::SUCCESS;
    }
}
