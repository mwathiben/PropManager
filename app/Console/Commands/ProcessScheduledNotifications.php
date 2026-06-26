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

        $counters = ['sent' => 0, 'failed' => 0];

        Notification::readyToSend()
            ->with('recipient:id,name')
            ->chunkById(100, function ($notifications) use ($notificationService, $dryRun, &$counters) {
                foreach ($notifications as $notification) {
                    $this->processNotification($notification, $notificationService, $dryRun, $counters);
                }
            });

        if (! $dryRun) {
            $this->newLine();
            $this->info("Completed: {$counters['sent']} sent, {$counters['failed']} failed.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{sent: int, failed: int}  $counters
     */
    private function processNotification(
        Notification $notification,
        NotificationService $notificationService,
        bool $dryRun,
        array &$counters,
    ): void {
        $this->line("Processing notification #{$notification->id} for {$notification->recipient?->name}...");

        if ($dryRun) {
            $this->info("  [DRY RUN] Would send: {$notification->subject}");

            return;
        }

        $this->attemptSend($notification, $notificationService, $counters);
    }

    /**
     * @param  array{sent: int, failed: int}  $counters
     */
    private function attemptSend(
        Notification $notification,
        NotificationService $notificationService,
        array &$counters,
    ): void {
        try {
            $success = $notificationService->sendDeferredNotification($notification);

            if ($success) {
                $counters['sent']++;
                $this->info("  Sent successfully via {$notification->channel}");
            } else {
                $counters['failed']++;
                $this->warn('  Failed to send');
            }
        } catch (\Exception $e) {
            $counters['failed']++;
            $this->error("  Error: {$e->getMessage()}");
        }
    }
}
