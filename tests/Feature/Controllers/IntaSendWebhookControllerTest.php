<?php

namespace Tests\Feature\Controllers;

use App\Enums\InvoiceStatus;
use App\Events\IntaSendPaymentStatusChanged;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Models\IntaSendTransaction;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class IntaSendWebhookControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected PaymentConfiguration $paymentConfig;

    protected string $webhookChallenge = 'test_webhook_challenge_12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_12345',
            'intasend_secret_key' => 'ISSecretKey_test_12345',
            'intasend_webhook_challenge' => $this->webhookChallenge,
            'intasend_environment' => 'sandbox',
            'accepted_payment_methods' => ['mobile_money'],
        ]);

        Mail::fake();
        Event::fake([PaymentReceivedEvent::class, IntaSendPaymentStatusChanged::class]);
    }

    protected function createWebhookPayload(
        IntaSendTransaction $transaction,
        string $state = 'COMPLETE',
        ?string $challenge = null
    ): array {
        return [
            'invoice_id' => $transaction->intasend_invoice_id,
            'state' => $state,
            'provider' => 'M-PESA',
            'charges' => '0.00',
            'net_amount' => (string) $transaction->amount,
            'currency' => 'KES',
            'value' => (string) $transaction->amount,
            'account' => $transaction->phone_number,
            'api_ref' => $transaction->api_ref,
            'mpesa_reference' => 'QKL'.rand(100000000, 999999999),
            'failed_reason' => null,
            'challenge' => $challenge ?? $this->webhookChallenge,
        ];
    }

    public function test_webhook_accepts_valid_complete_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $transaction->refresh();
        $this->assertEquals(IntaSendTransaction::STATE_COMPLETE, $transaction->state);
        $this->assertNotNull($transaction->payment_id);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 15000,
            'payment_method' => 'mobile_money',
        ]);

        $invoice->refresh();
        $this->assertEquals(15000, $invoice->amount_paid);
    }

    public function test_webhook_rejects_invalid_challenge(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create();

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE', 'wrong_challenge');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_idempotency_prevents_duplicate_payments(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $existingPayment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 15000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'INTASEND-TEST',
        ]);

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'payment_id' => $existingPayment->id,
            'state' => IntaSendTransaction::STATE_COMPLETE,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $this->assertEquals(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_webhook_handles_pending_state(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'PENDING');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $transaction->refresh();
        $this->assertEquals(IntaSendTransaction::STATE_PENDING, $transaction->state);
        $this->assertNull($transaction->payment_id);
    }

    public function test_webhook_handles_processing_state(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'PROCESSING');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $transaction->refresh();
        $this->assertEquals(IntaSendTransaction::STATE_PROCESSING, $transaction->state);
        $this->assertNull($transaction->payment_id);
    }

    public function test_webhook_handles_failed_state_with_reason(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'state' => IntaSendTransaction::STATE_PROCESSING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'FAILED');
        $payload['failed_reason'] = 'User cancelled the request';

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $transaction->refresh();
        $this->assertEquals(IntaSendTransaction::STATE_FAILED, $transaction->state);
        $this->assertEquals('User cancelled the request', $transaction->failure_reason);
        $this->assertNull($transaction->payment_id);
    }

    public function test_webhook_updates_invoice_to_paid_when_fully_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => $invoice->total_due,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = (string) $invoice->total_due;
        $payload['net_amount'] = (string) $invoice->total_due;

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
    }

    public function test_webhook_updates_invoice_to_partial_when_underpaid(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $partialAmount = $invoice->total_due - 5000;

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => $partialAmount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = (string) $partialAmount;
        $payload['net_amount'] = (string) $partialAmount;

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Partial, $invoice->status);
        $this->assertEquals($partialAmount, $invoice->amount_paid);
    }

    public function test_overpayment_credits_to_wallet(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 5000;
        $paymentAmount = $invoice->total_due + $overpaymentAmount;

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => $paymentAmount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = (string) $paymentAmount;
        $payload['net_amount'] = (string) $paymentAmount;

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $invoice->refresh();
        $lease->refresh();

        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
        $this->assertEquals($overpaymentAmount, $lease->wallet_balance);
    }

    public function test_webhook_creates_platform_fee_record(): void
    {
        config(['billing.collect_platform_fee' => true]);
        config(['billing.transaction_fee_percentage' => 3.0]);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 10000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = '10000';
        $payload['net_amount'] = '10000';

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);

        $this->assertDatabaseHas('platform_fees', [
            'payment_id' => $payment->id,
        ]);
    }

    public function test_webhook_returns_200_for_unknown_api_ref(): void
    {
        $payload = [
            'invoice_id' => 'UNKNOWN123',
            'state' => 'COMPLETE',
            'provider' => 'M-PESA',
            'value' => '10000',
            'api_ref' => 'ITS-9999999999-UNKNOWN',
            'challenge' => $this->webhookChallenge,
        ];

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();
    }

    public function test_webhook_dispatches_payment_received_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        Event::assertDispatched(PaymentReceivedEvent::class);
    }

    public function test_webhook_sends_payment_received_email(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        Mail::assertQueued(PaymentReceived::class);
    }

    public function test_webhook_creates_receipt(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $payment = Payment::where('invoice_id', $invoice->id)->first();

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_complete_webhook_dispatches_intasend_status_changed_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        Event::assertDispatched(IntaSendPaymentStatusChanged::class, function ($event) use ($transaction) {
            return $event->intasendInvoiceId === $transaction->intasend_invoice_id
                && $event->status === 'success'
                && $event->paymentId !== null
                && $event->amount === 15000.0
                && $event->mpesaReceipt !== null;
        });
    }

    public function test_failed_webhook_dispatches_intasend_status_changed_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PROCESSING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'FAILED');
        $payload['failed_reason'] = 'Insufficient funds';

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        Event::assertDispatched(IntaSendPaymentStatusChanged::class, function ($event) use ($transaction) {
            return $event->intasendInvoiceId === $transaction->intasend_invoice_id
                && $event->status === 'failed'
                && $event->failureReason === 'Insufficient funds';
        });
    }

    public function test_processing_webhook_dispatches_intasend_status_changed_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'PROCESSING');

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        Event::assertDispatched(IntaSendPaymentStatusChanged::class, function ($event) use ($transaction) {
            return $event->intasendInvoiceId === $transaction->intasend_invoice_id
                && $event->status === 'processing'
                && $event->amount === 15000.0;
        });
    }

    public function test_overpayment_notification_shows_correct_wallet_balance(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $initialBalance = 1000.00;
        $lease->wallet_balance = $initialBalance;
        $lease->save();

        $overpaymentAmount = 500.00;
        $paymentAmount = $invoice->total_due + $overpaymentAmount;

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => $paymentAmount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = (string) $paymentAmount;
        $payload['net_amount'] = (string) $paymentAmount;

        $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $expectedBalance = $initialBalance + $overpaymentAmount;

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($expectedBalance) {
            return $mail->newWalletBalance === $expectedBalance;
        });

        $lease->refresh();
        $this->assertEquals($expectedBalance, $lease->wallet_balance);
    }

    public function test_overpayment_notification_not_sent_with_invalid_landlord_email(): void
    {
        $this->landlord->update(['email' => 'invalid-email']);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 500.00;
        $paymentAmount = $invoice->total_due + $overpaymentAmount;

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => $paymentAmount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = (string) $paymentAmount;
        $payload['net_amount'] = (string) $paymentAmount;

        $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        Mail::assertNotQueued(OverpaymentNotification::class);

        $lease->refresh();
        $this->assertEquals($overpaymentAmount, $lease->wallet_balance);
    }

    public function test_amount_mismatch_creates_dlq_entry(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = '20000';

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();

        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_INTASEND,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_amount_mismatch_returns_200(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $transaction = IntaSendTransaction::factory()->forInvoice($invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');
        $payload['value'] = '20000';

        $response = $this->postJson('/api/webhooks/intasend/mpesa', $payload);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'ok']);
    }
}
