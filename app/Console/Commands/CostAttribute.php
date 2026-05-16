<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LandlordUsageMetric;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-33 COST-ATTRIB-2: convert raw landlord_usage_metrics into a
 * single estimated_cost_kes gauge per landlord over the rolling
 * window. Multiplies per-metric values by config/cost.php rates.
 *
 * Cardinality: capped at the top 50 landlords by value so Prometheus
 * doesn't ingest 10k label sets every day on a multi-tenant system.
 */
class CostAttribute extends Command
{
    protected $signature = 'cost:attribute {--days=30 : rolling window for the cost projection}';

    protected $description = 'Phase-33 COST-ATTRIB-2: emit per-landlord estimated_cost_kes gauges.';

    public function handle(MetricsService $metrics): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->toDateString();
        $rates = (array) config('cost.rates');

        $rows = DB::table('landlord_usage_metrics')
            ->select('landlord_id', 'metric', DB::raw('SUM(value) as total'))
            ->where('day', '>=', $cutoff)
            ->groupBy('landlord_id', 'metric')
            ->get();

        $costs = [];
        foreach ($rows as $row) {
            $costs[$row->landlord_id] = ($costs[$row->landlord_id] ?? 0.0)
                + $this->kesFor((string) $row->metric, (int) $row->total, $rates);
        }

        // Cap label cardinality at top 50 landlords by cost.
        arsort($costs);
        $top = array_slice($costs, 0, 50, preserve_keys: true);

        foreach ($top as $landlordId => $cost) {
            $metrics->gauge('landlord_estimated_cost_kes', round($cost, 2), [
                'landlord_id' => (string) $landlordId,
            ]);
        }

        $this->info(sprintf('Attributed cost for %d landlords (top 50 emitted as gauges).', count($costs)));

        return self::SUCCESS;
    }

    private function kesFor(string $metric, int $value, array $rates): float
    {
        return match ($metric) {
            LandlordUsageMetric::METRIC_DB_QUERIES => ($value / 1_000_000) * (float) ($rates['kes_per_million_queries'] ?? 0),
            LandlordUsageMetric::METRIC_S3_BYTES => ($value / (1024 ** 3)) * (float) ($rates['kes_per_gb_s3_standard'] ?? 0),
            LandlordUsageMetric::METRIC_SMS_SENDS => $value * (float) ($rates['kes_per_sms'] ?? 0),
            LandlordUsageMetric::METRIC_CRON_MINUTES => $value * (float) ($rates['kes_per_cron_minute'] ?? 0),
            LandlordUsageMetric::METRIC_LOG_BYTES => ($value / (1024 ** 2)) * (float) ($rates['kes_per_mb_log'] ?? 0),
            default => 0.0,
        };
    }
}
