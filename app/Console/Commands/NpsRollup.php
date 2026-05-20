<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Growth\NpsScoreService;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-66 GROWTH-OBSERVABILITY-1: emit NPS gauges (platform + per active
 * landlord) and alert when the platform score turns negative on a
 * meaningful sample.
 */
class NpsRollup extends Command
{
    protected $signature = 'nps:rollup {--window=90}';

    protected $description = 'Emit NPS score/response-count/response-rate gauges (platform + per active landlord) and alert on a negative platform score.';

    public function handle(NpsScoreService $service, MetricsService $metrics, AlertFiringRecorder $alerts): int
    {
        $window = max(1, (int) $this->option('window'));

        $platform = $service->compute(null, $window);
        $this->emit($metrics, ['scope' => 'platform'], $platform);

        foreach ($service->activeLandlordIds($window) as $landlordId) {
            $this->emit(
                $metrics,
                ['scope' => 'landlord', 'landlord_id' => (string) $landlordId],
                $service->compute($landlordId, $window),
            );
        }

        // Negative platform NPS (more detractors than promoters), but only
        // once the sample is meaningful — a single grumpy early response
        // shouldn't page anyone.
        if ($platform['response_count'] >= 10 && $platform['score'] < 0) {
            $alerts->record(
                alertKey: 'nps_negative',
                value: (float) $platform['score'],
                threshold: 0.0,
                metadata: ['response_count' => $platform['response_count'], 'window_days' => $window],
            );
        } else {
            $alerts->resolve('nps_negative');
        }

        $this->info("nps:rollup platform score {$platform['score']} from {$platform['response_count']} responses.");

        return self::SUCCESS;
    }

    /**
     * @param  array{scope:string, landlord_id?:string}  $labels
     * @param  array{score:int, response_count:int, response_rate:float, breakdown: array<string,int>}  $row
     */
    private function emit(MetricsService $metrics, array $labels, array $row): void
    {
        $metrics->gauge('nps_score', (float) $row['score'], $labels);
        $metrics->gauge('nps_response_count', (float) $row['response_count'], $labels);
        $metrics->gauge('nps_response_rate', $row['response_rate'], $labels);
    }
}
