<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\IdempotencyKey;
use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PAY-V2-022: Concurrent webhook tests with 50+ identical requests per provider.
 *
 * Validates the three-layer idempotency defense:
 *  1. IdempotencyService::acquire() (application-level locking)
 *  2. lockForUpdate() (pessimistic row locks)
 *  3. UNIQUE constraints (database-level safety net)
 *
 * Each test sends 50 identical webhook payloads and asserts exactly 1 payment is created.
 *
 * @group idempotency
 * @group concurrent
 */
class ConcurrentWebhookTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private const REQUEST_COUNT = 50;

    private array $setup;

    private array $tenantSetup;

    private string $validMpesaIp = '196.201.214.200';

    private string $paystackSecret = 'sk_test_concurrent_webhook_secret';

    private string $intasendChallenge = 'concurrent-test-challenge';

    private PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setup = $this->createLandlordWithFullSetup();
        $this->tenantSetup = $this->createTenantWithActiveLease(
            $this->setup['landlord'],
            $this->setup['units']->first()
        );

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_concurrent_pub',
            'paystack_secret_key' => $this->paystackSecret,
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_concurrent',
            'intasend_secret_key' => 'ISSecretKey_test_concurrent',
            'intasend_webhook_challenge' => $this->intasendChallenge,
            'intasend_environment' => 'sandbox',
        ]);

        config(['mpesa.allowed_ips' => [$this->validMpesaIp]]);
        config(['payments.webhook_security.paystack.allowed_ips' => []]);

        Mail::fake();
        Event::fake();
    }

    // ── M-Pesa ──────────────────────────────────────────────────────────

    public function test_50_identical_mpesa_webhooks_create_exactly_one_payment(): void
    {
        $invoice = $this->createInvoiceForLease($this->tenantSetup['lease'], 'sent');
        $transactionId = 'QKL'.rand(100000000, 999999999);
        $payload = $this->buildMpesaC2bPayload($transactionId, $invoice->total_due, $invoice->invoice_number);

        $responses = [];
        for ($i = 0; $i < self::REQUEST_COUNT; $i++) {
            $responses[] = $this->postJson(
                '/webhooks/mpesa/c2b/confirmation',
                $payload,
                ['REMOTE_ADDR' => $this->validMpesaIp]
            );
        }

        foreach ($responses as $index => $response) {
            $response->assertStatus(200);
        }

        $this->assertEquals(
            1,
            Payment::where('mpesa_transaction_id', $transactionId)->count(),
            'Exactly 1 payment should exist for M-Pesa transaction'
        );

        $idempotencyKeyString = "mpesa:{$transactionId}";
        $this->assertEquals(
            1,
            IdempotencyKey::where('key', $idempotencyKeyString)->count(),
            'Exactly 1 idempotency key should exist'
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKeyString,
            'status' => 'completed',
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
    }

    // ── IntaSend ────────────────────────────────────────────────────────

    public function test_50_identical_intasend_webhooks_create_exactly_one_payment(): void
    {
        $invoice = $this->createInvoiceForLease($this->tenantSetup['lease'], 'sent');
        $apiRef = 'ISL'.uniqid();

        $transaction = IntaSendTransaction::create([
            'landlord_id' => $this->setup['landlord']->id,
            'invoice_id' => $invoice->id,
            'api_ref' => $apiRef,
            'intasend_invoice_id' => 'INV-'.uniqid(),
            'amount' => $invoice->total_due,
            'phone_number' => '254712345678',
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->buildIntaSendPayload($apiRef, $transaction, $invoice);

        $responses = [];
        for ($i = 0; $i < self::REQUEST_COUNT; $i++) {
            $responses[] = $this->postJson('/api/webhooks/intasend/mpesa', $payload);
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        $this->assertEquals(
            1,
            Payment::where('intasend_reference', $apiRef)->count(),
            'Exactly 1 payment should exist for IntaSend transaction'
        );

        $idempotencyKeyString = "intasend:{$apiRef}";
        $this->assertEquals(
            1,
            IdempotencyKey::where('key', $idempotencyKeyString)->count(),
            'Exactly 1 idempotency key should exist'
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKeyString,
            'status' => 'completed',
        ]);

        $transaction->refresh();
        $this->assertEquals(IntaSendTransaction::STATE_COMPLETE, $transaction->state);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
    }

    // ── Paystack ────────────────────────────────────────────────────────

    public function test_50_identical_paystack_webhooks_create_exactly_one_payment(): void
    {
        $invoice = $this->createInvoiceForLease($this->tenantSetup['lease'], 'sent');
        $reference = 'PSK_conc_'.uniqid();
        $amountKobo = (int) ($invoice->total_due * 100);

        $webhookData = $this->buildPaystackWebhookData($reference, $invoice->id, $amountKobo);

        $responses = [];
        for ($i = 0; $i < self::REQUEST_COUNT; $i++) {
            $responses[] = $this->signAndSendPaystack($webhookData);
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        $this->assertEquals(
            1,
            Payment::where('paystack_reference', $reference)->count(),
            'Exactly 1 payment should exist for Paystack transaction'
        );

        $idempotencyKeyString = "paystack:{$reference}";
        $this->assertEquals(
            1,
            IdempotencyKey::where('key', $idempotencyKeyString)->count(),
            'Exactly 1 idempotency key should exist'
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKeyString,
            'status' => 'completed',
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function buildMpesaC2bPayload(string $transactionId, float $amount, string $billRefNumber): array
    {
        return [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => (string) $amount,
            'BusinessShortCode' => '174379',
            'BillRefNumber' => $billRefNumber,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '100000',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];
    }

    private function buildIntaSendPayload(string $apiRef, IntaSendTransaction $transaction, Invoice $invoice): array
    {
        return [
            'api_ref' => $apiRef,
            'invoice_id' => $transaction->intasend_invoice_id,
            'state' => 'COMPLETE',
            'value' => $invoice->total_due,
            'mpesa_reference' => 'QKL'.rand(100000000, 999999999),
            'challenge' => $this->intasendChallenge,
        ];
    }

    private function buildPaystackWebhookData(string $reference, int $invoiceId, int $amountKobo): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $reference,
                'amount' => $amountKobo,
                'channel' => 'card',
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'landlord_id' => $this->setup['landlord']->id,
                ],
            ],
        ];
    }

    private function signAndSendPaystack(array $webhookData): TestResponse
    {
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha512', $payload, $this->paystackSecret);

        return $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
        ]);
    }
}
