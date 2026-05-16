<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-41 [GATEWAY-DEEP] cycle watchdog: consolidates the
 * invariants from this audit cycle so future gateway work has
 * one place to notice regressions.
 *
 * Invariants:
 *   - RECONCILE-DEEP: StripeService::listCharges + compareLedgers + TransactionAdapter
 *   - WEBHOOK-DEEP: 4 new webhook event handlers (payment_intent.succeeded,
 *     charge.refunded, invoice.payment_failed, charge.dispute.created)
 *   - CONNECT: StripeConnectService + payment_configurations.stripe_connect_* columns
 *     + account.updated webhook
 *   - CHECKOUT: payments.checkout.initialize route
 *   - PLAN-SYNC: stripe:plan-sync cron weekly Mon 04:35 + price.updated webhook
 *   - PaymentRefundedExternal event class
 */
class Phase41GatewayDeepSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_service_has_list_charges_method(): void
    {
        $this->assertTrue(method_exists(\App\Services\StripeService::class, 'listCharges'));
    }

    public function test_compare_ledgers_helper_exists(): void
    {
        $reflection = new \ReflectionClass(\App\Services\Reconciliation\PaymentReconciliationService::class);
        $this->assertTrue($reflection->hasMethod('compareLedgers'));
    }

    public function test_transaction_adapter_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\Reconciliation\TransactionAdapter::class));
        $this->assertTrue(method_exists(\App\Services\Reconciliation\TransactionAdapter::class, 'fromPaystack'));
        $this->assertTrue(method_exists(\App\Services\Reconciliation\TransactionAdapter::class, 'fromStripe'));
    }

    public function test_stripe_connect_service_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\StripeConnectService::class));
        foreach (['createExpressAccount', 'onboardingLink', 'syncAccountStatus'] as $method) {
            $this->assertTrue(
                method_exists(\App\Services\StripeConnectService::class, $method),
                "StripeConnectService::{$method} missing",
            );
        }
    }

    public function test_payment_configurations_has_stripe_connect_columns(): void
    {
        $cols = \Schema::getColumnListing('payment_configurations');
        foreach (['stripe_connect_account_id', 'stripe_connect_status', 'stripe_connect_charges_enabled', 'stripe_connect_payouts_enabled'] as $col) {
            $this->assertContains($col, $cols);
        }
    }

    public function test_payment_refunded_external_event_exists(): void
    {
        $this->assertTrue(class_exists(\App\Events\PaymentRefundedExternal::class));
    }

    public function test_payments_checkout_initialize_route_registered(): void
    {
        $this->assertTrue(Route::has('payments.checkout.initialize'));
    }

    public function test_stripe_plan_sync_scheduled_weekly_monday_0435(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'stripe:plan-sync'));
        $this->assertNotNull($entry);
        $this->assertSame('35 4 * * 1', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_webhook_controller_handles_all_phase41_events(): void
    {
        // Reflection scan — each handler method must exist on the controller.
        $reflection = new \ReflectionClass(\App\Http\Controllers\Webhooks\StripeWebhookController::class);
        $expected = [
            'handlePaymentIntentSucceeded',
            'handleChargeRefunded',
            'handleInvoicePaymentFailed',
            'handleChargeDisputeCreated',
            'handleAccountUpdated',
            'handlePriceUpdated',
        ];
        foreach ($expected as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "StripeWebhookController::{$method} missing",
            );
        }
    }
}
