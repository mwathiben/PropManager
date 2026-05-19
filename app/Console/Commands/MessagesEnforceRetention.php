<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageThread;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-63 INBOX-MOD-2: soft-delete messages older than the
 * per-landlord retention window. Mirrors the Phase 59
 * FILE-RETENTION pattern: chunk by landlord, derive effective
 * retention (column override OR config default), soft-delete in
 * one batch per landlord, emit per-landlord gauge.
 */
class MessagesEnforceRetention extends Command
{
    protected $signature = 'messages:enforce-retention {--dry-run : Report only}';

    protected $description = 'Phase-63: soft-delete messages past the landlord retention window (Kenya DPA aligned).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $platformDefault = (int) config('inbox.retention.default_days', 2557);
        $metrics = app(MetricsService::class);
        $totalDeleted = 0;

        User::query()
            ->where('role', 'landlord')
            ->select(['id', 'message_retention_days'])
            ->orderBy('id')
            ->chunkById(100, function ($landlords) use (
                $platformDefault,
                $dryRun,
                $metrics,
                &$totalDeleted,
            ) {
                foreach ($landlords as $landlord) {
                    $retentionDays = $landlord->message_retention_days ?? $platformDefault;
                    $cutoff = now()->subDays($retentionDays);

                    $threadIds = MessageThread::query()
                        ->where('landlord_id', $landlord->id)
                        ->pluck('id');

                    if ($threadIds->isEmpty()) {
                        continue;
                    }

                    $query = DB::table('messages')
                        ->whereIn('thread_id', $threadIds)
                        ->where('created_at', '<', $cutoff)
                        ->whereNull('deleted_at');

                    $count = (int) $query->count();
                    if ($count === 0) {
                        continue;
                    }

                    if (! $dryRun) {
                        $query->update(['deleted_at' => now()]);
                    }

                    $metrics->gauge(
                        'messages_enforce_retention_deleted_count',
                        $count,
                        ['landlord_id' => (string) $landlord->id],
                    );

                    $totalDeleted += $count;
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')."Phase-63 retention: {$totalDeleted} messages soft-deleted.");

        return self::SUCCESS;
    }
}
