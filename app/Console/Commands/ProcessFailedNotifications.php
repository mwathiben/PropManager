<?php

namespace App\Console\Commands;

use App\Jobs\FallbackNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessFailedNotifications extends Command
{
    protected $signature = 'notifications:process-failed
                            {--dry-run : Show what would be processed without dispatching jobs}
                            {--limit=100 : Maximum number of notifications to process}';

    protected $description = 'Process stuck notifications and trigger fallback channels';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Processing stuck/failed notifications...');

        $stuckNotifications = $this->fetchStuckNotifications($limit);

        if ($stuckNotifications->isEmpty()) {
            $this->info('No stuck notifications found.');

            return self::SUCCESS;
        }

        $stats = $this->processNotifications($stuckNotifications, $dryRun);

        $this->printSummary($stats);

        Log::channel('notifications')->info('ProcessFailedNotifications completed', [
            'stats' => $stats,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    private function fetchStuckNotifications(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::query()
            ->where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhere(function ($q) {
                        $q->whereIn('status', ['pending', 'sent'])
                            ->whereNotNull('timeout_at')
                            ->where('timeout_at', '<=', now());
                    });
            })
            ->where(function ($query) {
                $query->whereNull('fallback_channel')
                    ->orWhere('fallback_channel', '!=', Notification::CHANNEL_IN_APP);
            })
            ->limit($limit)
            ->get();
    }

    private function processNotifications(\Illuminate\Database\Eloquent\Collection $notifications, bool $dryRun): array
    {
        $stats = [
            'total' => $notifications->count(),
            'dispatched' => 0,
            'skipped' => 0,
            'by_channel' => [],
        ];

        foreach ($notifications as $notification) {
            $this->processOneNotification($notification, $dryRun, $stats);
        }

        return $stats;
    }

    private function processOneNotification(Notification $notification, bool $dryRun, array &$stats): void
    {
        $currentChannel = $notification->fallback_channel ?? $notification->channel;

        if ($this->shouldSkipNotification($notification, $currentChannel)) {
            $stats['skipped']++;

            return;
        }

        $nextChannel = $notification->getNextFallbackChannel();

        if ($nextChannel === null && $notification->hasExhaustedAllChannels()) {
            $this->warn("  Notification #{$notification->id}: All channels exhausted");
            $stats['skipped']++;

            return;
        }

        $stats['by_channel'][$currentChannel] = ($stats['by_channel'][$currentChannel] ?? 0) + 1;

        if ($this->dispatchFallback($notification, $currentChannel, $nextChannel, $dryRun)) {
            $stats['dispatched']++;
        }
    }

    private function shouldSkipNotification(Notification $notification, string $currentChannel): bool
    {
        if ($notification->shouldFallback()) {
            return false;
        }

        if ($notification->retry_count < (Notification::CHANNEL_MAX_RETRIES[$currentChannel] ?? 0)) {
            $this->line("  Skipping #{$notification->id}: still has retries left on {$currentChannel}");

            return true;
        }

        return false;
    }

    private function dispatchFallback(Notification $notification, string $currentChannel, ?string $nextChannel, bool $dryRun): bool
    {
        if ($dryRun) {
            $this->line("  [DRY RUN] Would dispatch fallback for #{$notification->id}: {$currentChannel} → {$nextChannel}");

            return false;
        }

        FallbackNotificationJob::dispatch($notification->id);
        $this->line("  Dispatched fallback for #{$notification->id}: {$currentChannel} → {$nextChannel}");

        return true;
    }

    private function printSummary(array $stats): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Found', $stats['total']],
                ['Dispatched', $stats['dispatched']],
                ['Skipped', $stats['skipped']],
            ]
        );

        if (! empty($stats['by_channel'])) {
            $this->printChannelTable($stats['by_channel']);
        }
    }

    private function printChannelTable(array $byChannel): void
    {
        $this->newLine();
        $this->info('By Original Channel:');
        $channelRows = [];
        foreach ($byChannel as $channel => $count) {
            $channelRows[] = [$channel, $count];
        }
        $this->table(['Channel', 'Count'], $channelRows);
    }
}
