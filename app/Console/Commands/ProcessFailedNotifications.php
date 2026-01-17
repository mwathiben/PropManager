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

        $stuckNotifications = Notification::query()
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

        if ($stuckNotifications->isEmpty()) {
            $this->info('No stuck notifications found.');

            return self::SUCCESS;
        }

        $stats = [
            'total' => $stuckNotifications->count(),
            'dispatched' => 0,
            'skipped' => 0,
            'by_channel' => [],
        ];

        foreach ($stuckNotifications as $notification) {
            $currentChannel = $notification->fallback_channel ?? $notification->channel;

            if (! $notification->shouldFallback()) {
                if ($notification->retry_count < (Notification::CHANNEL_MAX_RETRIES[$currentChannel] ?? 0)) {
                    $stats['skipped']++;
                    $this->line("  Skipping #{$notification->id}: still has retries left on {$currentChannel}");

                    continue;
                }
            }

            $nextChannel = $notification->getNextFallbackChannel();

            if ($nextChannel === null && $notification->hasExhaustedAllChannels()) {
                $this->warn("  Notification #{$notification->id}: All channels exhausted");
                $stats['skipped']++;

                continue;
            }

            $stats['by_channel'][$currentChannel] = ($stats['by_channel'][$currentChannel] ?? 0) + 1;

            if ($dryRun) {
                $this->line("  [DRY RUN] Would dispatch fallback for #{$notification->id}: {$currentChannel} → {$nextChannel}");
            } else {
                FallbackNotificationJob::dispatch($notification->id);
                $stats['dispatched']++;
                $this->line("  Dispatched fallback for #{$notification->id}: {$currentChannel} → {$nextChannel}");
            }
        }

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
            $this->newLine();
            $this->info('By Original Channel:');
            $channelRows = [];
            foreach ($stats['by_channel'] as $channel => $count) {
                $channelRows[] = [$channel, $count];
            }
            $this->table(['Channel', 'Count'], $channelRows);
        }

        Log::channel('notifications')->info('ProcessFailedNotifications completed', [
            'stats' => $stats,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
