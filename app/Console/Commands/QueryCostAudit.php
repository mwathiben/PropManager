<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QueryCostLog;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-33 COST-QUERY-2/3: aggregate rolling 24h of query_cost_logs by
 * route_class, compute scan-to-return ratio p50 + p90, emit gauges and
 * fire the high_query_scan_ratio alert when p90 > threshold.
 */
class QueryCostAudit extends Command
{
    protected $signature = 'query:cost-audit {--hours=24} {--threshold=1000}';

    protected $description = 'Phase-33 COST-QUERY-2/3: per-route-class scan-to-return ratio gauges + alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $threshold = max(1, (int) $this->option('threshold'));
        $since = now()->subHours($hours);

        $byClass = [];
        QueryCostLog::query()
            ->where('request_at', '>=', $since)
            ->cursor()
            ->each(function (QueryCostLog $row) use (&$byClass): void {
                $byClass[$row->route_class][] = $row->rows_returned > 0
                    ? ($row->rows_scanned / $row->rows_returned)
                    : (float) $row->rows_scanned;
            });

        $maxP90 = 0.0;
        $worstClass = null;

        foreach ($byClass as $class => $ratios) {
            $p50 = $this->percentile($ratios, 0.5);
            $p90 = $this->percentile($ratios, 0.9);
            $metrics->gauge('query_scan_to_return_ratio_p50', $p50, ['route_class' => $class]);
            $metrics->gauge('query_scan_to_return_ratio_p90', $p90, ['route_class' => $class]);
            $this->line(sprintf('%-12s samples=%d p50=%.2f p90=%.2f', $class, count($ratios), $p50, $p90));

            if ($p90 > $maxP90) {
                $maxP90 = $p90;
                $worstClass = $class;
            }
        }

        if ($maxP90 > $threshold && $worstClass !== null) {
            $recorder->record(
                alertKey: 'high_query_scan_ratio',
                value: $maxP90,
                threshold: (float) $threshold,
                metadata: ['route_class' => $worstClass],
            );
        } else {
            $recorder->resolve('high_query_scan_ratio');
        }

        $this->info(sprintf('Audited %d route class(es); worst p90 ratio = %.2f.', count($byClass), $maxP90));

        return self::SUCCESS;
    }

    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $idx = (int) floor($p * (count($values) - 1));

        return (float) $values[$idx];
    }
}
