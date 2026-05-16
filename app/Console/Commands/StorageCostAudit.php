<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-33 COST-STORAGE-3: convert storage_bytes_by_tier_total gauges
 * (set by storage:tier-policy) into a KES monthly projection per tier.
 *
 * Reads gauges shaped `storage_bytes_by_tier_total{disk=X,prefix=Y,
 * target_tier=Z,bucket=current|target}` and multiplies by the
 * per-GB-month rate from config/cost.php — `bucket=current` uses the
 * standard rate (data hasn't moved yet); `bucket=target` uses the
 * target_tier rate (cost projection assuming policy is applied).
 */
class StorageCostAudit extends Command
{
    protected $signature = 'storage:cost-audit';

    protected $description = 'Phase-33 COST-STORAGE-3: monthly KES projection per storage tier.';

    public function handle(MetricsService $metrics): int
    {
        $rates = config('cost.rates');
        $tierToRate = [
            'standard' => (float) $rates['kes_per_gb_s3_standard'],
            'ia' => (float) $rates['kes_per_gb_s3_ia'],
            'glacier' => (float) $rates['kes_per_gb_s3_glacier'],
            'deep_archive' => (float) $rates['kes_per_gb_s3_deep_archive'],
        ];

        $snapshot = $metrics->gaugeSnapshot();
        $kesByTier = ['standard' => 0.0, 'ia' => 0.0, 'glacier' => 0.0, 'deep_archive' => 0.0];

        foreach ($snapshot as $field => $value) {
            if (! preg_match('/^storage_bytes_by_tier_total(?:\{(.+)\})?$/', $field, $m)) {
                continue;
            }
            $labels = $this->parseLabels($m[1] ?? '');
            $bucket = $labels['bucket'] ?? 'current';
            $effectiveTier = $bucket === 'target' ? ($labels['target_tier'] ?? 'standard') : 'standard';
            $rate = $tierToRate[$effectiveTier] ?? $tierToRate['standard'];
            $gb = ((float) $value) / (1024 ** 3);
            $kesByTier[$effectiveTier] += $gb * $rate;
        }

        $total = 0.0;
        foreach ($kesByTier as $tier => $kes) {
            $rounded = round($kes, 2);
            $metrics->gauge('storage_estimated_monthly_kes', $rounded, ['tier' => $tier]);
            $this->line(sprintf('tier=%-12s kes=%.2f', $tier, $rounded));
            $total += $rounded;
        }

        $this->info(sprintf('Total projected monthly storage cost: KES %.2f', round($total, 2)));

        return self::SUCCESS;
    }

    private function parseLabels(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $labels = [];
        foreach (explode(',', $raw) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if ($k !== '') {
                $labels[$k] = $v;
            }
        }

        return $labels;
    }
}
