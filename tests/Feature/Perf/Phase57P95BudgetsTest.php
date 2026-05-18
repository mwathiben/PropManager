<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Services\Sre\BudgetEnforcementService;
use PHPUnit\Framework\TestCase;

/**
 * Phase-57 P95-BUDGETS-2 watchdog. Pure-function evaluator unit tests.
 * Lives as a plain PHPUnit TestCase (no Laravel bootstrap) since the
 * evaluator has zero dependencies.
 */
class Phase57P95BudgetsTest extends TestCase
{
    public function test_under_budget_marks_not_violating(): void
    {
        $service = new BudgetEnforcementService;
        // 100 requests in the 100ms bucket, all under 500ms budget.
        $histogram = [
            'http_request_ms_bucket{route_class=read_path,le=100}' => 100,
            'http_request_ms_bucket{route_class=read_path,le=250}' => 100,
            'http_request_ms_bucket{route_class=read_path,le=500}' => 100,
            'http_request_ms_bucket{route_class=read_path,le=+Inf}' => 100,
        ];
        $budgets = ['read_path' => 500];

        $result = $service->evaluate($histogram, $budgets);

        $this->assertFalse($result['read_path']['is_violating']);
        $this->assertLessThanOrEqual(100, $result['read_path']['observed_p95_ms']);
    }

    public function test_over_budget_marks_violating(): void
    {
        $service = new BudgetEnforcementService;
        // 95% of requests in the 1000ms bucket, well over 500ms budget.
        $histogram = [
            'http_request_ms_bucket{route_class=read_path,le=100}' => 5,
            'http_request_ms_bucket{route_class=read_path,le=250}' => 5,
            'http_request_ms_bucket{route_class=read_path,le=500}' => 5,
            'http_request_ms_bucket{route_class=read_path,le=1000}' => 100,
            'http_request_ms_bucket{route_class=read_path,le=+Inf}' => 100,
        ];
        $budgets = ['read_path' => 500];

        $result = $service->evaluate($histogram, $budgets);

        $this->assertTrue($result['read_path']['is_violating']);
        $this->assertGreaterThan(500, $result['read_path']['observed_p95_ms']);
        $this->assertSame(500, $result['read_path']['budget_ms']);
    }

    public function test_missing_budget_for_route_class_excluded_from_result(): void
    {
        $service = new BudgetEnforcementService;
        $histogram = [
            'http_request_ms_bucket{route_class=unmapped_class,le=100}' => 1,
            'http_request_ms_bucket{route_class=unmapped_class,le=+Inf}' => 1,
        ];
        $budgets = ['read_path' => 500];

        $result = $service->evaluate($histogram, $budgets);

        $this->assertArrayNotHasKey('unmapped_class', $result);
        $this->assertSame([], $result);
    }

    public function test_empty_histogram_returns_empty_result(): void
    {
        $service = new BudgetEnforcementService;
        $result = $service->evaluate([], ['read_path' => 500]);

        $this->assertSame([], $result);
    }

    public function test_p95_is_interpolated_within_bucket(): void
    {
        $service = new BudgetEnforcementService;
        // 50 requests at 250ms-bucket, 50 at 500ms-bucket. p95 = 95th percentile
        // of cumulative count 100 → lands at request #95, which is in the 500ms
        // bucket. Linear interpolation between 250 and 500.
        $histogram = [
            'http_request_ms_bucket{route_class=read_path,le=100}' => 0,
            'http_request_ms_bucket{route_class=read_path,le=250}' => 50,
            'http_request_ms_bucket{route_class=read_path,le=500}' => 100,
            'http_request_ms_bucket{route_class=read_path,le=+Inf}' => 100,
        ];
        $budgets = ['read_path' => 600];

        $result = $service->evaluate($histogram, $budgets);

        // p95 of 100 = 95; bucket 500 has cumulative 100; positionInBucket = (95-50)/50 = 0.9.
        // p95 = 250 + (500-250)*0.9 = 250 + 225 = 475.
        $this->assertEqualsWithDelta(475.0, $result['read_path']['observed_p95_ms'], 1.0);
        $this->assertFalse($result['read_path']['is_violating']);
    }

    public function test_command_signature_locks_phase57_cron_name(): void
    {
        $this->assertSame('slo:enforce-budgets', (new \App\Console\Commands\SloEnforceBudgets)->getName());
    }
}
