<?php

namespace Tests\Feature\Services;

use App\Mail\FailedWebhookAlert;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use App\Services\BillingModelService;
use App\Services\Payment\PaystackCallbackHandler;
use App\Services\Payment\PaystackHandlerResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class WebhookDeadLetterServiceIntegrationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected PaymentConfiguration $paymentConfig;

    protected string $paystackSecret = 'sk_test_dlq_integration';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
        Mail::fake();

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_dlq_pub_key',
            'paystack_secret_key' => $this->paystackSecret,
        ]);
    }

    public function test_paystack_amount_mismatch_creates_dlq_entry(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_dlq_mismatch_'.uniqid();

        $paystackAmountKobo = (int) ($invoice->total_due * 100) + 500;

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

        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_PAYSTACK,
            'event_type' => 'charge.success',
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_paystack_processing_exception_creates_dlq_entry(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_dlq_exception_'.uniqid();

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

        $this->mock(BillingModelService::class, function ($mock) {
            $mock->shouldReceive('calculatePlatformFee')
                ->andThrow(new \RuntimeException('Billing service unavailable'));
        });

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isError());
        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_PAYSTACK,
            'event_type' => 'charge.success',
            'error_class' => WebhookDeadLetter::ERROR_PERMANENT,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_dlq_capture_does_not_alter_existing_webhook_response(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_dlq_response_'.uniqid();

        $paystackAmountKobo = (int) ($invoice->total_due * 100) + 500;

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
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('Amount mismatch', $result->errorMessage);
    }

    public function test_dlq_email_alert_queued_on_first_failure(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $reference = 'PSK_dlq_email_'.uniqid();

        $paystackAmountKobo = (int) ($invoice->total_due * 100) + 500;

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
        $handler->processCallback($reference, $this->landlord->id);

        Mail::assertQueued(FailedWebhookAlert::class);
    }

    public function test_dlq_email_alert_throttled_on_rapid_failures(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'WILL_BE_REPLACED',
                    'amount' => (int) ($invoice->total_due * 100) + 500,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);

        $ref1 = 'PSK_dlq_throttle1_'.uniqid();
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $ref1,
                    'amount' => (int) ($invoice->total_due * 100) + 500,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);
        $handler->processCallback($ref1, $this->landlord->id);

        $ref2 = 'PSK_dlq_throttle2_'.uniqid();
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $ref2,
                    'amount' => (int) ($invoice->total_due * 100) + 500,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);
        $handler->processCallback($ref2, $this->landlord->id);

        Mail::assertQueued(FailedWebhookAlert::class, 1);
        $this->assertDatabaseCount('webhook_dead_letters', 2);
    }
}
