<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductEvent;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-35 PLATFORM-ANALYTICS-3: nightly rollup per event name.
 *
 * Emits product_event_count_24h{event=X} gauge for the top 30
 * event names by descending count. Operator-visible signal for
 * the Grafana product-analytics panel.
 */
class ProductAnalyticsRollup extends Command
{
    protected $signature = 'product:rollup';

    protected $description = 'Phase-35 PLATFORM-ANALYTICS-3: per-event 24h count gauge.';

    public function handle(MetricsService $metrics): int
    {
        $since = now()->subDay();

        $rows = ProductEvent::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>=', $since)
            ->selectRaw('event_name, COUNT(*) AS count')
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        foreach ($rows as $row) {
            $metrics->gauge(
                'product_event_count_24h',
                (float) $row->count,
                ['event' => $row->event_name],
            );
        }

        $this->info(sprintf('Rolled up %d event name(s).', $rows->count()));

        return self::SUCCESS;
    }
}
