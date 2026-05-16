<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-40 [STRIPE-GATEWAY] cycle watchdog: consolidates invariants
 * from this cycle so future Stripe/gateway work has one place to
 * notice regressions.
 *
 * Invariants:
 *   - GATEWAY-CONTRACT: PaymentMethod::Stripe + stripe in supportedGateways
 *   - GATEWAY-STRIPE-3: payment_configurations has stripe_* columns
 *   - GATEWAY-WEBHOOK-2: /webhooks/v2/stripe route exists
 *   - GATEWAY-CURRENCY-1: subscription_plans.stripe_plan_code + subscriptions.stripe_subscription_code
 *   - GATEWAY-RECONCILE-2: payments:gateway-reconcile scheduled daily 05:45
 *   - GATEWAY-RECONCILE-3: gateway_drift alert registered
 *   - GATEWAY-PREF-1: users.payment_gateway_preference column
 *   - GATEWAY-PREF-2: admin.gateways.{index,update} routes
 *   - lang/{en,sw}/payments.php parity
 */
class Phase40GatewaySurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_method_enum_has_stripe(): void
    {
        $this->assertContains('stripe', \App\Enums\PaymentMethod::values());
    }

    public function test_payment_gateway_manager_supports_stripe(): void
    {
        $this->assertTrue(app(\App\Services\PaymentGatewayManager::class)->supports('stripe'));
    }

    public function test_payment_configurations_table_has_stripe_columns(): void
    {
        $cols = Schema::getColumnListing('payment_configurations');
        foreach (['stripe_enabled', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_subscription_plans_table_has_stripe_plan_code(): void
    {
        $this->assertContains('stripe_plan_code', Schema::getColumnListing('subscription_plans'));
    }

    public function test_subscriptions_table_has_stripe_codes(): void
    {
        $cols = Schema::getColumnListing('subscriptions');
        $this->assertContains('stripe_subscription_code', $cols);
        $this->assertContains('stripe_customer_code', $cols);
    }

    public function test_users_table_has_payment_gateway_preference(): void
    {
        $this->assertContains('payment_gateway_preference', Schema::getColumnListing('users'));
    }

    public function test_stripe_webhook_route_is_registered(): void
    {
        $this->assertTrue(Route::has('webhooks.v2.stripe'));
    }

    public function test_admin_gateways_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.gateways.index'));
        $this->assertTrue(Route::has('admin.gateways.update'));
    }

    public function test_payments_gateway_reconcile_scheduled_daily_at_0545(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'payments:gateway-reconcile'));
        $this->assertNotNull($entry, 'payments:gateway-reconcile must be scheduled');
        $this->assertSame('45 5 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_gateway_drift_alert_registered(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $this->assertContains('gateway_drift', $registry);
    }

    public function test_payments_lang_namespace_has_parity(): void
    {
        $en = require lang_path('en/payments.php');
        $sw = require lang_path('sw/payments.php');
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'lang/{en,sw}/payments.php key order must match.',
        );
    }

    public function test_stripe_php_sdk_installed(): void
    {
        $this->assertTrue(class_exists(\Stripe\StripeClient::class));
        $this->assertTrue(class_exists(\Stripe\Webhook::class));
    }

    private function flatten(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
