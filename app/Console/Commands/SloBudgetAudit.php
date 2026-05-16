<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceSlo;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use App\Services\Sre\ErrorBudgetCalculator;
use Illuminate\Console\Command;

/**
 * Phase-32 SRE-BUDGET-2/3: emit per-service budget + burn-rate gauges
 * AND, when the multi-window burn-rate threshold is crossed, fire the
 * slo_budget_fast_burn alert through AlertFiringRecorder.
 *
 *   - service_slo_budget_remaining_pct{service=X}
 *   - service_slo_burn_rate_1h{service=X}
 *   - service_slo_burn_rate_6h{service=X}
 *
 * Multi-window burn-rate rule (Google SRE Workbook):
 *   burn_rate_1h > 14.4 AND burn_rate_6h > 6.0 -> page on-call
 */
class SloBudgetAudit extends Command
{
    protected $signature = 'slo:budget-audit';

    protected $description = 'Phase-32 SRE-BUDGET-2/3: per-service SLO budget gauges + multi-window burn-rate alert.';

    public const FAST_BURN_1H_THRESHOLD = 14.4;
    public const FAST_BURN_6H_THRESHOLD = 6.0;

    public function handle(
        ErrorBudgetCalculator $calculator,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $slos = ServiceSlo::query()->where('is_active', true)->get();
        $burning = 0;

        foreach ($slos as $slo) {
            $result = $calculator->compute($slo);
            $labels = ['service' => $slo->service_key];

            $metrics->gauge('service_slo_budget_remaining_pct', $result['budget_remaining_pct'], $labels);
            $metrics->gauge('service_slo_burn_rate_1h', $result['burn_rate_1h'], $labels);
            $metrics->gauge('service_slo_burn_rate_6h', $result['burn_rate_6h'], $labels);

            if ($result['burn_rate_1h'] > self::FAST_BURN_1H_THRESHOLD
                && $result['burn_rate_6h'] > self::FAST_BURN_6H_THRESHOLD) {
                $burning++;
                $recorder->record(
                    alertKey: 'slo_budget_fast_burn',
                    value: $result['burn_rate_1h'],
                    threshold: self::FAST_BURN_1H_THRESHOLD,
                    metadata: [
                        'service' => $slo->service_key,
                        'burn_rate_1h' => $result['burn_rate_1h'],
                        'burn_rate_6h' => $result['burn_rate_6h'],
                        'budget_remaining_pct' => $result['budget_remaining_pct'],
                    ],
                );
            }

            $this->line(sprintf(
                '%-30s remaining=%s%% burn_1h=%s burn_6h=%s',
                $slo->service_key,
                $result['budget_remaining_pct'],
                $result['burn_rate_1h'],
                $result['burn_rate_6h'],
            ));
        }

        $this->info("Services in fast-burn territory: {$burning}");

        return self::SUCCESS;
    }
}
