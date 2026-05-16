<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Growth\ChurnService;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-34 GROWTH-CHURN-3: weekly churn + cohort retention audit.
 *
 *   - Emits subscription_monthly_churn_rate (last completed month).
 *   - Emits subscription_cohort_m1|m3|m6_retention gauges (rolled up
 *     across all cohorts present for that month-since-signup).
 *   - Fires high_churn_rate alert (sev2) when monthly rate > 0.05.
 *
 * B2B SaaS benchmark: monthly churn > 5% is bad news; > 10% is a
 * fire. Sev2 because revenue impact compounds — operator needs a
 * page, not just email.
 */
class ChurnAudit extends Command
{
    protected $signature = 'churn:audit {--threshold=0.05}';

    protected $description = 'Phase-34 GROWTH-CHURN-3: monthly churn rate + cohort retention gauges + high_churn_rate alert.';

    public function handle(ChurnService $service, MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $threshold = max(0.0, min(1.0, (float) $this->option('threshold')));

        $monthlyRate = $service->monthlyChurnRate();
        $metrics->gauge('subscription_monthly_churn_rate', $monthlyRate);
        $this->line(sprintf('Monthly churn rate (last completed month): %.4f', $monthlyRate));

        $cohorts = $service->subscriptionCohorts(12);
        $bucketM1 = $this->averageRetentionAtMonth($cohorts, 1);
        $bucketM3 = $this->averageRetentionAtMonth($cohorts, 3);
        $bucketM6 = $this->averageRetentionAtMonth($cohorts, 6);
        $metrics->gauge('subscription_cohort_m1_retention', $bucketM1);
        $metrics->gauge('subscription_cohort_m3_retention', $bucketM3);
        $metrics->gauge('subscription_cohort_m6_retention', $bucketM6);
        $this->line(sprintf('Cohort retention: m1=%.3f m3=%.3f m6=%.3f', $bucketM1, $bucketM3, $bucketM6));

        if ($monthlyRate > $threshold) {
            $recorder->record(
                alertKey: 'high_churn_rate',
                value: $monthlyRate,
                threshold: $threshold,
                metadata: ['monthly_rate' => $monthlyRate, 'm1' => $bucketM1, 'm3' => $bucketM3, 'm6' => $bucketM6],
            );
            $this->warn(sprintf('FIRED high_churn_rate: %.4f > %.4f', $monthlyRate, $threshold));
        } else {
            $recorder->resolve('high_churn_rate');
        }

        return self::SUCCESS;
    }

    private function averageRetentionAtMonth(array $cohorts, int $m): float
    {
        $values = [];
        foreach ($cohorts as $cohort) {
            if (isset($cohort['retention'][$m])) {
                $values[] = $cohort['retention'][$m];
            }
        }
        if ($values === []) {
            return 0.0;
        }

        return round(array_sum($values) / count($values), 4);
    }
}
