<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductEvent;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-37 PWA-RETENTION-STATS-1: weekly product_events prune.
 * Deletes rows older than config('platform.product_events_retention_days')
 * in chunks to avoid long-running transactions. Cold-storage
 * rollover (PWA-RETENTION-STATS-2) runs monthly BEFORE this so
 * historical data is preserved on Storage::disk('archive').
 */
class ProductEventsPrune extends Command
{
    protected $signature = 'product:prune {--days=} {--chunk=5000} {--dry-run}';

    protected $description = 'Phase-37 PWA-RETENTION-STATS-1: delete product_events older than the configured retention window.';

    public function handle(MetricsService $metrics): int
    {
        $days = (int) ($this->option('days') ?? config('platform.product_events_retention_days', 180));
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);

        if ($dryRun) {
            $count = ProductEvent::query()
                ->withoutGlobalScopes()
                ->where('created_at', '<', $cutoff)
                ->count();
            $this->info(sprintf('[dry-run] Would prune %d row(s) older than %s.', $count, $cutoff->toIso8601String()));
            $metrics->gauge('product_events_pruned_count', 0);

            return self::SUCCESS;
        }

        $totalDeleted = 0;
        do {
            $deleted = ProductEvent::query()
                ->withoutGlobalScopes()
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $metrics->gauge('product_events_pruned_count', $totalDeleted);
        $this->info(sprintf('Pruned %d product_events row(s) older than %s.', $totalDeleted, $cutoff->toIso8601String()));

        return self::SUCCESS;
    }
}
