<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Models\AlertFiring;
use App\Models\ServiceSlo;
use App\Services\Sre\ErrorBudgetCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase32ErrorBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_returns_full_budget_remaining_with_no_alerts(): void
    {
        $slo = $this->slo(99.9, 30, 'dependency_down');

        $result = app(ErrorBudgetCalculator::class)->compute($slo);

        $this->assertSame(99.9, $result['target_pct']);
        $expectedBudget = 30 * 24 * 60 * 0.001;
        $this->assertEqualsWithDelta($expectedBudget, $result['budget_total_minutes'], 0.01);
        $this->assertSame(100.0, $result['budget_remaining_pct']);
        $this->assertSame(0.0, $result['burn_rate_1h']);
    }

    public function test_calculator_subtracts_open_alert_duration_from_budget(): void
    {
        $slo = $this->slo(99.0, 30, 'dependency_down');
        $now = CarbonImmutable::now();

        AlertFiring::create([
            'alert_key' => 'dependency_down',
            'severity' => 'sev2',
            'value' => 0,
            'threshold' => 0,
            'fired_at' => $now->subMinutes(30),
            'resolved_at' => $now->subMinutes(10),
        ]);

        $result = app(ErrorBudgetCalculator::class)->compute($slo, $now);

        $this->assertGreaterThan(0, $result['budget_consumed_minutes']);
        $this->assertLessThan(100.0, $result['budget_remaining_pct']);
    }

    public function test_calculator_burn_rate_1h_reacts_to_recent_alerts(): void
    {
        $slo = $this->slo(99.0, 30, 'dependency_down');
        $now = CarbonImmutable::now();

        AlertFiring::create([
            'alert_key' => 'dependency_down',
            'severity' => 'sev2',
            'value' => 0,
            'threshold' => 0,
            'fired_at' => $now->subMinutes(40),
            'resolved_at' => $now->subMinutes(5),
        ]);

        $result = app(ErrorBudgetCalculator::class)->compute($slo, $now);

        $this->assertGreaterThan(0, $result['burn_rate_1h']);
        $this->assertGreaterThan(0, $result['burn_rate_6h']);
    }

    public function test_slo_budget_audit_runs_with_seeded_slos(): void
    {
        $this->slo(99.9, 30, 'dependency_down', 'payment_webhook_handlers');

        $exit = \Artisan::call('slo:budget-audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('payment_webhook_handlers', $output);
        $this->assertStringContainsString('remaining=', $output);
    }

    public function test_slo_budget_audit_fires_fast_burn_alert_when_thresholds_crossed(): void
    {
        $slo = $this->slo(99.99, 30, 'dependency_down', 'payment_webhook_handlers');
        $now = CarbonImmutable::now();

        // Open dependency_down alert for the entire last hour exhausts
        // a very-tight 99.99% budget hard.
        AlertFiring::create([
            'alert_key' => 'dependency_down',
            'severity' => 'sev2',
            'value' => 0,
            'threshold' => 0,
            'fired_at' => $now->subMinutes(60),
            'resolved_at' => null,
        ]);

        $exit = \Artisan::call('slo:budget-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Services in fast-burn territory: 1', $output);
        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'slo_budget_fast_burn',
        ]);
    }

    private function slo(float $objective, int $windowDays, string $badMetric, string $key = 'svc'): ServiceSlo
    {
        return ServiceSlo::updateOrCreate(
            ['service_key' => $key],
            [
                'service_key' => $key,
                'tier' => ServiceSlo::TIER_2,
                'window_days' => $windowDays,
                'objective_pct' => $objective,
                'bad_indicator_metric' => $badMetric,
                'is_active' => true,
            ],
        );
    }
}
