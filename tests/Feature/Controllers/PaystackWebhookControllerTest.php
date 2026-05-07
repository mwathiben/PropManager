<?php

namespace Tests\Feature\Controllers;

use App\Enums\InvoiceStatus;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Mail\PaymentReceived;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaystackWebhookControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected PaymentConfiguration $paymentConfig;

    protected string $paystackSecret = 'sk_test_webhook_controller_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_webhook_ctrl_pub',
            'paystack_secret_key' => $this->paystackSecret,
        ]);

        config(['payments.webhook_security.paystack.allowed_ips' => ['127.0.0.1']]);

        Mail::fake();
        Event::fake([PaymentReceivedEvent::class]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildWebhookData(
        string $reference,
        int $invoiceId,
        int $amountKobo,
        string $event = 'charge.success'
    ): array {
        return [
            'event' => $event,
            'data' => [
                'status' => 'success',
                'reference' => $reference,
                'amount' => $amountKobo,
                'channel' => 'card',
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];
    }

    private function signAndSend(array $webhookData, ?string $signatureOverride = null): TestResponse
    {
        $payload = json_encode($webhookData);
        $signature = $signatureOverride ?? hash_hmac('sha512', $payload, $this->paystackSecret);

        return $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
        ]);
    }

    private function createLeaseAndInvoice(string $status = 'sent'): array
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, $status);

        return compact('tenant', 'lease', 'invoice');
    }

    // ── Group 1: Happy Path ──────────────────────────────────────────

    public function test_valid_webhook_creates_payment(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_ctrl_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $response = $this->signAndSend($data);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
            'payment_method' => 'paystack',
        ]);
    }

    public function test_full_payment_sets_invoice_to_paid(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_paid_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $this->signAndSend($data);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
    }

    public function test_creates_receipt_for_payment(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_rcpt_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $this->signAndSend($data);

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertNotNull($payment);

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_overpayment_credits_to_lease_wallet(): void
    {
        ['lease' => $lease, 'invoice' => $invoice] = $this->createLeaseAndInvoice('partial');

        $priorPayment = 20000;
        $invoice->update(['amount_paid' => $priorPayment]);

        $reference = 'PSK_over_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $this->signAndSend($data);

        $invoice->refresh();
        $lease->refresh();

        $expectedOverpayment = $invoice->total_due - ($invoice->total_due - $priorPayment);

        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
        $this->assertEquals($expectedOverpayment, $lease->wallet_balance);
    }

    public function test_dispatches_payment_received_event(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_evt_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $this->signAndSend($data);

        Event::assertDispatched(PaymentReceivedEvent::class);
    }

    public function test_sends_payment_received_email(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_mail_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $this->signAndSend($data);

        Mail::assertQueued(PaymentReceived::class);
    }

    // ── Group 2: Security & Validation ───────────────────────────────

    public function test_invalid_signature_returns_401(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_badsig_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);
        $response = $this->signAndSend($data, 'invalid_signature_here');

        $response->assertStatus(401);
        $this->assertDatabaseMissing('payments', ['paystack_reference' => $reference]);
    }

    public function test_missing_signature_returns_401(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $data = $this->buildWebhookData('PSK_nosig', $invoice->id, 2500000);

        $response = $this->postJson('/webhooks/paystack', $data);

        $response->assertStatus(401);
    }

    public function test_missing_landlord_id_returns_400(): void
    {
        $data = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_noll_'.uniqid(),
                'amount' => 2500000,
                'metadata' => [],
            ],
        ];

        $response = $this->signAndSend($data);

        $response->assertStatus(400);
    }

    public function test_unconfigured_landlord_returns_400(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $data = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_noconfig_'.uniqid(),
                'amount' => 2500000,
                'metadata' => ['landlord_id' => $otherLandlord->id],
            ],
        ];

        $payload = json_encode($data);
        $signature = hash_hmac('sha512', $payload, 'arbitrary_key');

        $response = $this->postJson('/webhooks/paystack', $data, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(400);
    }

    public function test_malformed_json_returns_400(): void
    {
        $rawBody = 'not-valid-json{';
        $signature = hash_hmac('sha512', $rawBody, $this->paystackSecret);

        $response = $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody);

        $response->assertStatus(400);
    }

    // ── Group 3: Idempotency ─────────────────────────────────────────

    public function test_duplicate_webhook_is_idempotent(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_dup_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $data = $this->buildWebhookData($reference, $invoice->id, $amountKobo);

        $first = $this->signAndSend($data);
        $first->assertOk();

        $second = $this->signAndSend($data);
        $second->assertOk();

        $this->assertEquals(1, Payment::where('paystack_reference', $reference)->count());
    }

    // ── Group 4: Edge Cases ──────────────────────────────────────────

    public function test_non_charge_success_event_returns_ignored(): void
    {
        $data = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PSK_transfer_'.uniqid(),
                'metadata' => ['landlord_id' => $this->landlord->id],
            ],
        ];

        $response = $this->signAndSend($data);

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
    }

    public function test_amount_mismatch_creates_dlq_entry(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_mismatch_'.uniqid();
        $mismatchKobo = (int) ($invoice->total_due * 100) + 200;

        $data = $this->buildWebhookData($reference, $invoice->id, $mismatchKobo);
        $response = $this->signAndSend($data);

        $response->assertStatus(400);

        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_PAYSTACK,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertDatabaseMissing('payments', ['paystack_reference' => $reference]);
    }

    public function test_amount_within_tolerance_processes_normally(): void
    {
        ['invoice' => $invoice] = $this->createLeaseAndInvoice();
        $reference = 'PSK_tol_'.uniqid();
        $tolerantKobo = (int) ($invoice->total_due * 100) + 50;

        $data = $this->buildWebhookData($reference, $invoice->id, $tolerantKobo);
        $response = $this->signAndSend($data);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['paystack_reference' => $reference]);
    }

    public function test_missing_invoice_id_returns_ignored(): void
    {
        $data = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_noinv_'.uniqid(),
                'amount' => 2500000,
                'channel' => 'card',
                'metadata' => [
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];

        $response = $this->signAndSend($data);

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
    }
}
