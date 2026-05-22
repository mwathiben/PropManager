<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\RefundStatus;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\PaymentDispute;
use App\Models\ReconciliationReport;
use App\Models\Refund;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-85 PAYMENTS-GATEWAY-DEPTH: reconciliation view, Stripe in the daily run,
 * idempotent refund retry, dispute recording + notification.
 */
class Phase85PaymentsGatewayDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private string $secret = 'whsec_test_secret_12345';

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->secret]);
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    private function signPayload(array $payload): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.json_encode($payload), $this->secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_landlord_views_own_reconciliation_reports(): void
    {
        $report = Model::withoutEvents(fn () => ReconciliationReport::create([
            'landlord_id' => $this->landlord->id,
            'provider' => 'paystack',
            'status' => 'completed',
            'period_from' => now()->subDay(),
            'period_to' => now(),
            'local_count' => 5,
            'remote_count' => 6,
            'matched_count' => 5,
            'discrepancy_count' => 1,
            'result_data' => [['type' => 'missing_locally', 'reference' => 'PSK_1', 'local_amount' => null, 'remote_amount' => 1000.0, 'currency' => 'KES', 'remote_status' => 'success']],
            'reconciled_at' => now(),
        ]));

        $this->actingAs($this->landlord)
            ->get(route('gateway-reconciliation.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finances/GatewayReconciliation/Index')->has('reports.data', 1));

        $this->actingAs($this->landlord)
            ->get(route('gateway-reconciliation.show', $report->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finances/GatewayReconciliation/Show')->has('report.discrepancies', 1));

        $other = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord']));
        $resp = $this->actingAs($other)->get(route('gateway-reconciliation.show', $report->id));
        $this->assertContains($resp->status(), [403, 404]);
    }

    public function test_failed_refund_retry_is_idempotent(): void
    {
        $payment = Model::withoutEvents(fn () => Payment::create([
            'lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id, 'amount' => 5000,
            'payment_method' => 'cash', 'payment_date' => now(), 'reference' => 'M1',
        ]));
        $invoice = $this->createInvoiceForLease($this->lease);

        // Reference-less failed refund → safe to retry (manual cash → completes).
        $refund = Model::withoutEvents(fn () => Refund::create([
            'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'landlord_id' => $this->landlord->id,
            'amount' => 100, 'status' => RefundStatus::Failed, 'reason' => 'x', 'payment_method' => 'cash', 'initiated_by' => $this->landlord->id,
        ]));

        app(\App\Services\RefundService::class)->retry($refund->fresh());
        $refund->refresh();
        $this->assertSame(1, $refund->retry_count);
        $this->assertSame(RefundStatus::Completed, $refund->status);

        // Referenced failed refund → NEVER re-called (double-refund guard).
        $referenced = Model::withoutEvents(fn () => Refund::create([
            'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'landlord_id' => $this->landlord->id,
            'amount' => 100, 'status' => RefundStatus::Failed, 'reason' => 'x', 'payment_method' => 'paystack',
            'paystack_refund_reference' => 'RF_123', 'initiated_by' => $this->landlord->id,
        ]));

        $this->assertFalse(app(\App\Services\RefundService::class)->retry($referenced->fresh()));
        $referenced->refresh();
        $this->assertTrue($referenced->needs_review);
        $this->assertSame(RefundStatus::Failed, $referenced->status);
    }

    public function test_retry_failed_command_exits_zero(): void
    {
        $this->artisan('refunds:retry-failed')->assertExitCode(0);
        $this->artisan('payments:reconciliation-rollup')->assertExitCode(0);
    }

    public function test_daily_reconciliation_includes_stripe(): void
    {
        Model::withoutEvents(fn () => PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'stripe_enabled' => true,
            'stripe_secret_key' => 'sk_test_x',
        ]));

        $fake = Mockery::mock(StripeService::class);
        $fake->shouldReceive('withConfig')->andReturnSelf();
        $fake->shouldReceive('listCharges')->andReturn([]);
        $this->app->instance(StripeService::class, $fake);

        $this->artisan('reconciliation:run-daily', ['--landlord' => $this->landlord->id])->assertExitCode(0);

        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $this->landlord->id,
            'provider' => 'stripe',
        ]);
    }

    public function test_stripe_dispute_webhook_records_and_notifies(): void
    {
        $payment = Model::withoutEvents(fn () => Payment::create([
            'lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id, 'amount' => 5000,
            'payment_method' => 'stripe', 'payment_date' => now(), 'reference' => 'pi_abc', 'paystack_reference' => 'pi_abc',
        ]));

        $payload = [
            'id' => 'evt_dispute_1',
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_1', 'charge' => 'ch_1', 'payment_intent' => 'pi_abc',
                'amount' => 500000, 'currency' => 'usd', 'reason' => 'fraudulent', 'status' => 'needs_response',
            ]],
        ];

        $this->postJson('/webhooks/v2/stripe', $payload, ['Stripe-Signature' => $this->signPayload($payload)])
            ->assertOk();

        $this->assertDatabaseHas('payment_disputes', [
            'gateway_dispute_id' => 'dp_1',
            'payment_id' => $payment->id,
            'landlord_id' => $this->landlord->id,
            'status' => PaymentDispute::STATUS_OPEN,
        ]);
        $this->assertTrue(
            Notification::where('recipient_id', $this->landlord->id)
                ->where('type', Notification::TYPE_PAYMENT_DISPUTE)->exists(),
        );

        // dispute.closed → resolution recorded.
        $closed = [
            'id' => 'evt_dispute_2',
            'type' => 'charge.dispute.closed',
            'data' => ['object' => ['id' => 'dp_1', 'status' => 'lost']],
        ];
        $this->postJson('/webhooks/v2/stripe', $closed, ['Stripe-Signature' => $this->signPayload($closed)])->assertOk();
        $this->assertSame(PaymentDispute::STATUS_LOST, PaymentDispute::where('gateway_dispute_id', 'dp_1')->first()->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
