<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Events\PaymentRefundedExternal;
use App\Models\Lease;
use App\Models\OperationalIncident;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-41 GATEWAY-WEBHOOK-DEEP-1/2/3/4: payment_intent.succeeded +
 * charge.refunded + invoice.payment_failed + charge.dispute.created
 * handlers on StripeWebhookController.
 */
class Phase41WebhookDeepTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_phase41_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    private function signPayload(array $payload, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $payloadJson = json_encode($payload);
        $signedPayload = $timestamp.'.'.$payloadJson;
        $signature = hash_hmac('sha256', $signedPayload, $this->secret);

        return "t={$timestamp},v1={$signature}";
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->signPayload($payload)],
            json_encode($payload));
    }

    public function test_payment_intent_succeeded_creates_local_payment(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id]);
        $intentId = 'pi_test_'.uniqid();

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => $intentId,
                'amount' => 15000,
                'currency' => 'usd',
                'metadata' => [
                    'landlord_id' => (string) $landlord->id,
                    'lease_id' => (string) $lease->id,
                ],
            ]],
        ];

        $response = $this->postWebhook($payload);

        $response->assertStatus(200);
        $payment = Payment::query()->where('paystack_reference', $intentId)->first();
        $this->assertNotNull($payment);
        $this->assertSame('stripe', $payment->payment_method->value ?? $payment->payment_method);
        $this->assertEquals(150.0, (float) $payment->amount);
    }

    public function test_payment_intent_succeeded_is_idempotent_per_intent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id]);
        $intentId = 'pi_dedup_'.uniqid();

        $base = [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => $intentId,
                'amount' => 5000,
                'currency' => 'usd',
                'metadata' => [
                    'landlord_id' => (string) $landlord->id,
                    'lease_id' => (string) $lease->id,
                ],
            ]],
        ];

        // Two separate event IDs (so the dedup cache doesn't bounce them),
        // same intent — handler must NOT double-create.
        $this->postWebhook($base + ['id' => 'evt_1_'.uniqid()]);
        $this->postWebhook($base + ['id' => 'evt_2_'.uniqid()]);

        $this->assertSame(1, Payment::query()->where('paystack_reference', $intentId)->count());
    }

    public function test_charge_refunded_flips_payment_to_voided_and_dispatches_event(): void
    {
        Event::fake([PaymentRefundedExternal::class]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id]);
        $intentId = 'pi_refund_'.uniqid();
        $payment = Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'payment_date' => now(),
            'reference' => $intentId,
            'paystack_reference' => $intentId,
            'is_voided' => false,
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_'.uniqid(),
                'payment_intent' => $intentId,
                'amount_refunded' => 10000,
                'currency' => 'usd',
            ]],
        ];

        $this->postWebhook($payload)->assertStatus(200);

        $payment->refresh();
        $this->assertTrue((bool) $payment->is_voided);
        $this->assertNotNull($payment->voided_at);
        Event::assertDispatched(PaymentRefundedExternal::class);
    }

    public function test_invoice_payment_failed_marks_subscription_past_due(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create();
        $subCode = 'sub_failed_'.uniqid();
        $sub = Subscription::factory()->create([
            'user_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_code' => $subCode,
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_'.uniqid(),
                'subscription' => $subCode,
            ]],
        ];

        $this->postWebhook($payload)->assertStatus(200);

        $fresh = $sub->fresh();
        $this->assertSame('past_due', $fresh->status->value ?? $fresh->status);
    }

    public function test_charge_dispute_created_logs_operational_incident(): void
    {
        $chargeId = 'ch_dispute_'.uniqid();
        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_'.uniqid(),
                'charge' => $chargeId,
                'amount' => 5000,
                'currency' => 'usd',
                'reason' => 'fraudulent',
            ]],
        ];

        $this->postWebhook($payload)->assertStatus(200);

        $incident = OperationalIncident::query()
            ->where('title', 'like', '%'.$chargeId.'%')
            ->first();
        $this->assertNotNull($incident);
        $this->assertSame('sev3', $incident->severity);
        $this->assertSame('open', $incident->status);
        $this->assertContains('stripe', $incident->affected_services);
    }

    public function test_unknown_event_type_still_returns_200(): void
    {
        $payload = [
            'id' => 'evt_unknown_'.uniqid(),
            'type' => 'some.future.event',
            'data' => ['object' => ['id' => 'whatever']],
        ];

        $this->postWebhook($payload)->assertStatus(200);
    }
}
