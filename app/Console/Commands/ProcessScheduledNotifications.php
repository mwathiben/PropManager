<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-scheduled
                            {--dry-run : Show what would be processed without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled notifications that are ready to send (quiet hours safety net)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $dryRun = $this->option('dry-run');

        $totalCount = Notification::readyToSend()->count();

        if ($totalCount === 0) {
            $this->info('No scheduled notifications ready to send.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} scheduled notification(s) ready to send.");

        $sent = 0;
        $failed = 0;

        Notification::readyToSend()
            ->with('recipient:id,name')
            ->chunkById(100, function ($notifications) use ($notificationService, $dryRun, &$sent, &$failed) {
                foreach ($notifications as $notification) {
                    $this->line("Processing notification #{$notification->id} for {$notification->recipient?->name}...");

                    if ($dryRun) {
                        $this->info("  [DRY RUN] Would send: {$notification->subject}");

                        continue;
                    }

                    try {
                        $success = $notificationService->sendDeferredNotification($notification);

                        if ($success) {
                            $sent++;
                            $this->info("  Sent successfully via {$notification->channel}");
                        } else {
                            $failed++;
                            $this->warn('  Failed to send');
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $this->error("  Error: {$e->getMessage()}");
                    }
                }
            });

        if (! $dryRun) {
            $this->newLine();
            $this->info("Completed: {$sent} sent, {$failed} failed.");
        }

        return self::SUCCESS;
    }
}
