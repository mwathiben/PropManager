<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\BudgetEnforcementService;
use Illuminate\Console\Command;

/**
 * Phase-57 P95-BUDGETS-1: nightly enforce-budgets cron.
 *
 * Compares observed p95 per route_class to config('observability.slo.latency_budgets_ms')
 * and emits route_p95_violation{route_class} gauge (1=violating, 0=compliant).
 *
 * Idempotent + read-only — no DB writes, no side effects beyond the gauge emit.
 */
class SloEnforceBudgets extends Command
{
    protected $signature = 'slo:enforce-budgets';

    protected $description = 'Compare observed p95 to per-route-class SLO budgets + emit route_p95_violation gauge.';

    public function handle(MetricsService $metrics, BudgetEnforcementService $evaluator): int
    {
        $histogram = $metrics->snapshot();
        $budgets = (array) config('observability.slo.latency_budgets_ms', []);
        if ($budgets === []) {
            $this->warn('No latency budgets configured; skipping.');

            return self::SUCCESS;
        }

        $verdicts = $evaluator->evaluate($histogram, $budgets);

        $violating = 0;
        foreach ($verdicts as $routeClass => $verdict) {
            $metrics->gauge(
                'route_p95_violation',
                $verdict['is_violating'] ? 1.0 : 0.0,
                ['route_class' => $routeClass],
            );
            if ($verdict['is_violating']) {
                $violating++;
                $this->warn(sprintf(
                    '%s: observed p95 %.1fms > budget %dms',
                    $routeClass,
                    $verdict['observed_p95_ms'],
                    $verdict['budget_ms'],
                ));
            }
        }

        $this->info(sprintf(
            'Evaluated %d route classes; %d violating.',
            count($verdicts),
            $violating,
        ));

        return self::SUCCESS;
    }
}
