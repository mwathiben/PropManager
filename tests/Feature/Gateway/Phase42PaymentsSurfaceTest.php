<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-42 [PAYMENTS-INTL] cycle watchdog — consolidates the
 * invariants of this audit cycle so future payments work has
 * one place to notice regressions.
 *
 * Invariants:
 *   - TAX: invoice_items tax_amount_cents + tax_rate_bps columns;
 *     payment_configurations kra_pin + vat_rate_bps_override +
 *     stripe_tax_enabled; StripeTaxService::computeKenyanVat.
 *   - PLAN-SYNC-AUTO: subscription_plans.drift_resolve_mode +
 *     subscription_plan_drift_log table + PlanDriftResolver +
 *     admin.gateways.plan-drift-mode route.
 *   - CONNECT-STANDARD: stripe_connect_account_type column +
 *     StripeConnectAccountType enum + StripeConnectStandardService.
 *   - CART: checkout_sessions + checkout_session_items +
 *     CartCheckoutService + checkout.sessions.initialize route.
 *   - METHODS: stripe_customers + StripeCustomerService +
 *     3 customer.* webhook handlers.
 *   - PAYOUT-AUDIT: payouts:stripe-balance-audit cron at 15 3,15
 *     Africa/Nairobi + payout.failed webhook handler.
 */
class Phase42PaymentsSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_TABLES = [
        'subscription_plan_drift_log',
        'checkout_sessions',
        'checkout_session_items',
        'stripe_customers',
    ];

    private const EXPECTED_PAYMENT_CONFIG_COLUMNS = [
        'kra_pin',
        'vat_rate_bps_override',
        'stripe_tax_enabled',
        'stripe_connect_account_type',
    ];

    private const EXPECTED_INVOICE_ITEM_COLUMNS = [
        'tax_amount_cents',
        'tax_rate_bps',
    ];

    private const EXPECTED_SUBSCRIPTION_PLAN_COLUMNS = [
        'drift_resolve_mode',
    ];

    private const EXPECTED_NEW_SERVICES = [
        \App\Services\Tax\StripeTaxService::class,
        \App\Services\Subscriptions\PlanDriftResolver::class,
        \App\Services\StripeConnectStandardService::class,
        \App\Services\Checkout\CartCheckoutService::class,
        \App\Services\StripeCustomerService::class,
    ];

    private const EXPECTED_NEW_MODELS = [
        \App\Models\SubscriptionPlanDriftLog::class,
        \App\Models\CheckoutSession::class,
        \App\Models\CheckoutSessionItem::class,
        \App\Models\StripeCustomer::class,
    ];

    private const EXPECTED_NEW_ENUMS = [
        \App\Enums\DriftResolveMode::class,
        \App\Enums\StripeConnectAccountType::class,
    ];

    public function test_all_expected_phase_42_tables_exist(): void
    {
        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Phase-42 table {$table} missing");
        }
    }

    public function test_payment_configurations_carries_all_phase_42_columns(): void
    {
        $cols = \Schema::getColumnListing('payment_configurations');
        foreach (self::EXPECTED_PAYMENT_CONFIG_COLUMNS as $col) {
            $this->assertContains($col, $cols, "payment_configurations.{$col} missing");
        }
    }

    public function test_invoice_items_carries_phase_42_tax_columns(): void
    {
        $cols = \Schema::getColumnListing('invoice_items');
        foreach (self::EXPECTED_INVOICE_ITEM_COLUMNS as $col) {
            $this->assertContains($col, $cols, "invoice_items.{$col} missing");
        }
    }

    public function test_subscription_plans_carries_drift_resolve_mode(): void
    {
        $cols = \Schema::getColumnListing('subscription_plans');
        foreach (self::EXPECTED_SUBSCRIPTION_PLAN_COLUMNS as $col) {
            $this->assertContains($col, $cols, "subscription_plans.{$col} missing");
        }
    }

    public function test_all_expected_phase_42_services_exist(): void
    {
        foreach (self::EXPECTED_NEW_SERVICES as $cls) {
            $this->assertTrue(class_exists($cls), "Phase-42 service class {$cls} missing");
        }
    }

    public function test_all_expected_phase_42_models_exist(): void
    {
        foreach (self::EXPECTED_NEW_MODELS as $cls) {
            $this->assertTrue(class_exists($cls), "Phase-42 model class {$cls} missing");
        }
    }

    public function test_all_expected_phase_42_enums_exist(): void
    {
        foreach (self::EXPECTED_NEW_ENUMS as $cls) {
            $this->assertTrue(enum_exists($cls), "Phase-42 enum {$cls} missing");
        }
    }

    public function test_payout_balance_audit_command_registered(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\StripeBalanceAudit::class));

        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'payouts:stripe-balance-audit'));
        $this->assertNotNull($entry, 'payouts:stripe-balance-audit must be scheduled');
        $this->assertSame('15 3,15 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_admin_gateways_tax_config_route_registered(): void
    {
        $this->assertTrue(Route::has('admin.gateways.tax-config'));
    }

    public function test_admin_gateways_plan_drift_mode_route_registered(): void
    {
        $this->assertTrue(Route::has('admin.gateways.plan-drift-mode'));
    }

    public function test_checkout_sessions_initialize_route_registered(): void
    {
        $this->assertTrue(Route::has('checkout.sessions.initialize'));
    }

    public function test_payments_lang_file_has_all_phase_42_subkeys(): void
    {
        foreach (['en', 'sw'] as $locale) {
            $payments = require base_path("lang/{$locale}/payments.php");
            foreach (['tax', 'plan_sync', 'cart', 'methods', 'payout'] as $key) {
                $this->assertArrayHasKey($key, $payments, "lang/{$locale}/payments.php missing '{$key}' subtree");
                $this->assertIsArray($payments[$key]);
                $this->assertNotEmpty($payments[$key]);
            }
        }
    }

    public function test_payments_runbook_has_phase_42_sections(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/payments.md'));
        $this->assertIsString($runbook);
        // Phase 42 sections appended to payments.md
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] TAX', $runbook);
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] PLAN-SYNC-AUTO', $runbook);
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] CONNECT-STANDARD', $runbook);
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] CART', $runbook);
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] METHODS', $runbook);
        $this->assertStringContainsString('Phase 42 [PAYMENTS-INTL] PAYOUT-AUDIT', $runbook);
    }

    public function test_alert_thresholds_runbook_has_payout_failure_row(): void
    {
        $alerts = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('Stripe Connect payout failures', $alerts);
        $this->assertStringContainsString('stripe_payout_failure_count', $alerts);
    }
}
