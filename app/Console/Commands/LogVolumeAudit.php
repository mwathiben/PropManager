<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LogVolumeDaily;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-33 COST-LOGS-2/3: per-landlord log-volume audit.
 *
 *   - Reads last 24h from log_volume_daily.
 *   - Emits landlord_log_bytes_24h gauge for top 20 landlords.
 *   - Emits landlord_log_bytes_median + landlord_log_bytes_p95
 *     distribution gauges so the operator dashboard plots both
 *     the absolute level and the spread.
 *   - Fires high_landlord_log_volume alert if any landlord exceeds
 *     config('cost.high_landlord_multiplier') × the median.
 *
 * Same skew-detection pattern as Phase-32 alert:quality but applied
 * to log volume (the noisy-minority signal).
 */
class LogVolumeAudit extends Command
{
    protected $signature = 'log:volume-audit';

    protected $description = 'Phase-33 COST-LOGS-2: per-landlord log volume gauges + skew alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $since = now()->subDay();

        $rows = LogVolumeDaily::query()
            ->withoutGlobalScopes()
            ->where('day', '>=', $since->toDateString())
            ->selectRaw('landlord_id, SUM(byte_count) AS bytes')
            ->groupBy('landlord_id')
            ->orderByDesc('bytes')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No log volume rows in last 24h — nothing to audit.');
            $recorder->resolve('high_landlord_log_volume');

            return self::SUCCESS;
        }

        $bytes = $rows->pluck('bytes')->map(fn ($v) => (int) $v)->all();
        $median = $this->percentile($bytes, 0.5);
        $p95 = $this->percentile($bytes, 0.95);

        $this->emitDistributionGauges($metrics, $rows, $median, $p95);

        $multiplier = (float) config('cost.high_landlord_multiplier', 5.0);
        $threshold = $median > 0 ? $median * $multiplier : 0.0;
        $offenders = $this->collectOffenders($rows, $threshold);

        $this->fireOrResolveAlert($recorder, $offenders, $threshold, ['median' => $median, 'p95' => $p95]);

        $this->info(sprintf(
            'Audited %d landlord(s). median=%dB p95=%dB offenders=%d',
            $rows->count(),
            $median,
            $p95,
            count($offenders),
        ));

        return self::SUCCESS;
    }

    /** @param \Illuminate\Support\Collection<int, \App\Models\LogVolumeDaily> $rows */
    private function emitDistributionGauges(MetricsService $metrics, $rows, int $median, int $p95): void
    {
        $metrics->gauge('landlord_log_bytes_median', (float) $median);
        $metrics->gauge('landlord_log_bytes_p95', (float) $p95);

        foreach ($rows->take(20) as $row) {
            $metrics->gauge(
                'landlord_log_bytes_24h',
                (float) $row->bytes,
                ['landlord_id' => (string) $row->landlord_id],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LogVolumeDaily>  $rows
     * @return array<int, int>
     */
    private function collectOffenders($rows, float $threshold): array
    {
        if ($threshold <= 0) {
            return [];
        }

        $offenders = [];
        foreach ($rows as $row) {
            if ((int) $row->bytes >= $threshold) {
                $offenders[(int) $row->landlord_id] = (int) $row->bytes;
            }
        }

        return $offenders;
    }

    /**
     * @param  array<int, int>  $offenders
     * @param  array{median:int,p95:int}  $distribution
     */
    private function fireOrResolveAlert(AlertFiringRecorder $recorder, array $offenders, float $threshold, array $distribution): void
    {
        if ($offenders !== []) {
            $worst = max($offenders);
            $recorder->record(
                alertKey: 'high_landlord_log_volume',
                value: (float) $worst,
                threshold: $threshold,
                metadata: ['landlord_ids' => array_keys($offenders), 'median' => $distribution['median'], 'p95' => $distribution['p95']],
            );
        } else {
            $recorder->resolve('high_landlord_log_volume');
        }
    }

    private function percentile(array $values, float $p): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $idx = (int) floor(($p) * (count($values) - 1));

        return (int) $values[$idx];
    }
}
