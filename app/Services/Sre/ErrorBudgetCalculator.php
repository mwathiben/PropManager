<?php

declare(strict_types=1);

namespace App\Services\Sre;

use App\Models\ServiceSlo;
use Carbon\CarbonImmutable;

/**
 * Phase-32 SRE-BUDGET-1: compute remaining error budget + multi-window
 * burn rates per service. The math:
 *
 *   target_pct       = ServiceSlo.objective_pct
 *   window_minutes   = window_days * 24 * 60
 *   budget_minutes   = window_minutes * (1 - target_pct / 100)
 *   consumed_minutes = sum of bad-indicator outages within window
 *   remaining_pct    = 100 * (budget_minutes - consumed_minutes) / budget_minutes
 *
 * Multi-window burn rate (Google SRE Workbook):
 *   burn_rate_1h = (consumed last 1h / budget_minutes) * window_minutes / 60
 *   burn_rate_6h = (consumed last 6h / budget_minutes) * window_minutes / 360
 *
 * For Phase 32 we use AlertFiring duration as the bad-indicator
 * proxy — every minute an alert was open counts as one minute of
 * consumption. This gives a real number to alert on while the
 * gauge-derived SLI machinery matures.
 */
class ErrorBudgetCalculator
{
    /**
     * @return array{
     *     service_key: string,
     *     tier: string,
     *     target_pct: float,
     *     window_days: int,
     *     budget_total_minutes: float,
     *     budget_consumed_minutes: float,
     *     budget_remaining_pct: float,
     *     burn_rate_1h: float,
     *     burn_rate_6h: float,
     * }
     */
    public function compute(ServiceSlo $slo, ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $windowStart = $now->subDays($slo->window_days);

        $budgetTotal = $slo->window_days * 24 * 60 * (1.0 - $slo->objective_pct / 100.0);

        $consumedFull = $this->consumedMinutes($slo, $windowStart, $now);
        $remainingPct = $budgetTotal > 0
            ? max(0.0, 100.0 * ($budgetTotal - $consumedFull) / $budgetTotal)
            : 100.0;

        $consumed1h = $this->consumedMinutes($slo, $now->subHour(), $now);
        $consumed6h = $this->consumedMinutes($slo, $now->subHours(6), $now);

        $burn1h = $budgetTotal > 0 ? ($consumed1h / $budgetTotal) * (($slo->window_days * 24 * 60) / 60.0) : 0.0;
        $burn6h = $budgetTotal > 0 ? ($consumed6h / $budgetTotal) * (($slo->window_days * 24 * 60) / 360.0) : 0.0;

        return [
            'service_key' => $slo->service_key,
            'tier' => $slo->tier,
            'target_pct' => $slo->objective_pct,
            'window_days' => $slo->window_days,
            'budget_total_minutes' => round($budgetTotal, 2),
            'budget_consumed_minutes' => round($consumedFull, 2),
            'budget_remaining_pct' => round($remainingPct, 3),
            'burn_rate_1h' => round($burn1h, 3),
            'burn_rate_6h' => round($burn6h, 3),
        ];
    }

    private function consumedMinutes(ServiceSlo $slo, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $metric = $slo->bad_indicator_metric;
        if ($metric === null) {
            return 0.0;
        }

        // Convention: the bad_indicator_metric is the alert_key of the
        // SLO-impacting alert (e.g. 'dependency_down' for payment
        // webhooks). Sum the minutes those alerts were open in the
        // window.
        $firings = \App\Models\AlertFiring::query()
            ->where('alert_key', $metric)
            ->where('fired_at', '<', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('resolved_at')->orWhere('resolved_at', '>', $start);
            })
            ->get();

        $consumed = 0.0;
        foreach ($firings as $firing) {
            $segStart = $firing->fired_at->greaterThan($start) ? $firing->fired_at : $start;
            $segEnd = $firing->resolved_at === null
                ? $end
                : ($firing->resolved_at->lessThan($end) ? $firing->resolved_at : $end);
            $consumed += abs($segStart->diffInMinutes($segEnd));
        }

        return $consumed;
    }
}
