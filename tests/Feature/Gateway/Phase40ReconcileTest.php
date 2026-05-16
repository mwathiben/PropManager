<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Services\Reconciliation\PaymentReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-RECONCILE-1/2/3: reconcile dispatcher +
 * payments:gateway-reconcile cron + gateway_drift alert registration.
 */
class Phase40ReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_dispatcher_routes_stripe_to_stripe_method(): void
    {
        $result = app(PaymentReconciliationService::class)->reconcile(
            'stripe',
            999999,
            CarbonImmutable::now()->subDay()->startOfDay(),
            CarbonImmutable::now()->subDay()->endOfDay(),
        );
        $this->assertSame(0, $result->localCount);
        $this->assertSame(0, count($result->discrepancies));
    }

    public function test_reconcile_dispatcher_throws_on_unknown_gateway(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentReconciliationService::class)->reconcile(
            'venmo',
            1,
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now(),
        );
    }

    public function test_payments_gateway_reconcile_cron_is_scheduled_daily_at_0545(): void
    {
        $entry = collect(\Illuminate\Support\Facades\Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'payments:gateway-reconcile'));

        $this->assertNotNull($entry, 'payments:gateway-reconcile must be scheduled');
        $this->assertSame('45 5 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_gateway_drift_alert_is_registered(): void
    {
        $alerts = collect(config('alerts.alerts'))->keyBy('key');
        $this->assertTrue($alerts->has('gateway_drift'));
        $this->assertSame('sev3', $alerts->get('gateway_drift')['severity']);
        $this->assertSame('gateway_reconcile_drift_total', $alerts->get('gateway_drift')['gauge']);
    }

    public function test_payments_gateway_reconcile_command_runs_clean_with_no_configs(): void
    {
        $this->artisan('payments:gateway-reconcile')->assertExitCode(0);
    }
}
