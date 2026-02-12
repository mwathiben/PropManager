<?php

namespace Tests\Feature\Services;

use App\Enums\Currency;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Payment\PaystackCallbackHandler;
use App\Services\Payment\PaystackHandlerResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaystackCallbackHandlerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected PaymentConfiguration $paymentConfig;

    protected string $paystackSecret = 'sk_test_handler_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
        Mail::fake();

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_handler_pub_key',
            'paystack_secret_key' => $this->paystackSecret,
        ]);
    }

    // ── Group 1: Callback Flow ──────────────────────────────────────

    public function test_callback_processes_successful_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => (int) ($invoice->total_due * 100),
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->processResult);
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
        ]);

        $invoice->refresh();
        $this->assertEquals((float) $invoice->total_due, (float) $invoice->amount_paid);
    }

    public function test_callback_returns_not_configured_when_paystack_missing(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback('PSK_ref_123', $otherLandlord->id);

        $this->assertEquals(PaystackHandlerResult::STATUS_NOT_CONFIGURED, $result->status);
    }

    public function test_callback_returns_verification_failed_when_api_fails(): void
    {
        $reference = 'PSK_fail_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => false,
                'message' => 'Invalid key',
            ], 401),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertEquals(PaystackHandlerResult::STATUS_VERIFICATION_FAILED, $result->status);
    }

    public function test_callback_returns_error_when_payment_not_successful(): void
    {
        $reference = 'PSK_abandoned_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'abandoned',
                    'reference' => $reference,
                    'amount' => 500000,
                    'metadata' => ['invoice_id' => 1],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('verification failed', strtolower($result->errorMessage));
    }

    public function test_callback_detects_initial_payment_and_returns_data(): void
    {
        $reference = 'PSK_initial_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'channel' => 'card',
                    'metadata' => [
                        'type' => 'initial_payment',
                        'verification_id' => 42,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isInitialPayment());
        $this->assertEquals(42, $result->metadata['verification_id']);
    }

    public function test_callback_returns_already_processed_for_duplicate_reference(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_dup_'.uniqid();

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => $reference,
            'paystack_reference' => $reference,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => (int) ($invoice->total_due * 100),
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isAlreadyProcessed());
    }

    public function test_callback_returns_error_when_no_invoice_in_metadata(): void
    {
        $reference = 'PSK_noinv_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'channel' => 'card',
                    'metadata' => [
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isError());
    }

    // ── Group 2: Webhook Flow ───────────────────────────────────────

    public function test_webhook_processes_charge_success_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_wh_'.uniqid();

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $reference,
                'amount' => (int) ($invoice->total_due * 100),
                'channel' => 'card',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $this->paystackSecret);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook($payload, $signature);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook('{}', null);

        $this->assertEquals(PaystackHandlerResult::STATUS_UNAUTHORIZED, $result->status);
        $this->assertEquals(401, $result->httpStatus());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_bad_sig',
                'metadata' => ['landlord_id' => $this->landlord->id],
            ],
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook($payload, 'invalid_signature_here');

        $this->assertEquals(PaystackHandlerResult::STATUS_UNAUTHORIZED, $result->status);
        $this->assertEquals(401, $result->httpStatus());
    }

    public function test_webhook_rejects_missing_landlord_id_in_metadata(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_no_ll',
                'metadata' => [],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $this->paystackSecret);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook($payload, $signature);

        $this->assertEquals(PaystackHandlerResult::STATUS_BAD_REQUEST, $result->status);
        $this->assertEquals(400, $result->httpStatus());
    }

    public function test_webhook_rejects_unconfigured_landlord(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_unconfig',
                'metadata' => ['landlord_id' => $otherLandlord->id],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, 'some_random_secret');

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook($payload, $signature);

        $this->assertEquals(PaystackHandlerResult::STATUS_BAD_REQUEST, $result->status);
    }

    public function test_webhook_ignores_non_charge_events(): void
    {
        $payload = json_encode([
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PSK_transfer',
                'metadata' => ['landlord_id' => $this->landlord->id],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $this->paystackSecret);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processWebhook($payload, $signature);

        $this->assertTrue($result->isIgnored());
    }

    // ── Group 3: Multi-Currency ────────────────────────────────────

    public function test_callback_handles_usd_currency_in_response(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $invoice->update(['currency' => 'USD']);
        $reference = 'PSK_USD_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => (int) ($invoice->total_due * 100),
                    'currency' => 'USD',
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
            'currency' => 'USD',
        ]);

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertEquals((float) $invoice->total_due, (float) $payment->amount);
    }

    public function test_callback_defaults_to_kes_when_no_currency(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_NOCRCY_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => (int) ($invoice->total_due * 100),
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertEquals(Currency::KES, $payment->currency);
    }

    // ── Group 4: Amount Validation ──────────────────────────────────

    public function test_amount_within_tolerance_processes_successfully(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_tol_'.uniqid();

        $paystackAmountKobo = (int) ($invoice->total_due * 100) + 50; // 0.50 KES over

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => $paystackAmountKobo,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
    }

    public function test_amount_beyond_tolerance_returns_mismatch_error(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_mismatch_'.uniqid();

        $paystackAmountKobo = (int) ($invoice->total_due * 100) + 200; // 2.00 KES over

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => $paystackAmountKobo,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertEquals(PaystackHandlerResult::STATUS_AMOUNT_MISMATCH, $result->status);
    }

    public function test_exact_amount_match_processes_successfully(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_exact_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => (int) ($invoice->total_due * 100),
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
    }
}
