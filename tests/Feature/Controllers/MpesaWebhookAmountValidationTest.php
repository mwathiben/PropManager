<?php

namespace Tests\Feature\Controllers;

use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\WebhookDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class MpesaWebhookAmountValidationTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    private array $setupData;

    private $landlord;

    private $tenant;

    private $lease;

    private $invoice;

    private string $stkRoute = '/api/webhooks/mpesa/stk-callback';

    private string $c2bRoute = '/api/webhooks/mpesa/c2b/confirmation';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $tenantData = $this->createTenantWithActiveLease(
            $this->landlord,
            $this->setupData['units']->first()
        );
        $this->tenant = $tenantData['tenant'];
        $this->lease = $tenantData['lease'];
        $this->invoice = $this->createInvoiceForLease($this->lease);

        config(['mpesa.allowed_ips' => ['127.0.0.1']]);
        config(['payments.webhook_security.mpesa.timestamp_tolerance_minutes' => 999999]);

        Mail::fake();
        Event::fake();
    }

    private function createStkPaymentLink(string $checkoutRequestId): Payment
    {
        return Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 0,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'PENDING-STK-'.uniqid(),
            'mpesa_checkout_request_id' => $checkoutRequestId,
        ]);
    }

    public function test_stk_callback_accepts_exact_amount(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        $this->createStkPaymentLink($checkoutRequestId);
        // Use float to preserve cents - M-Pesa amounts may include decimals
        $exactAmount = round((float) $this->invoice->total_due, 2);

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            $exactAmount,
            '254712345678'
        );

        $initialPaymentCount = Payment::count();

        $response = $this->postJson($this->stkRoute, $payload);

        $response->assertOk();
        $this->assertEquals($initialPaymentCount + 1, Payment::count());
        $this->assertDatabaseMissing('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
        ]);
    }

    public function test_stk_callback_accepts_within_tolerance(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        $this->createStkPaymentLink($checkoutRequestId);
        // Cast to float before adding to preserve cents
        $withinTolerance = (float) $this->invoice->total_due + 0.50;

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            $withinTolerance,
            '254712345678'
        );

        $initialPaymentCount = Payment::count();

        $response = $this->postJson($this->stkRoute, $payload);

        $response->assertOk();
        $this->assertGreaterThan($initialPaymentCount, Payment::count());
        $this->assertDatabaseMissing('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
        ]);
    }

    public function test_stk_callback_flags_overpayment_beyond_tolerance(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        $this->createStkPaymentLink($checkoutRequestId);
        $overTolerance = (float) $this->invoice->total_due + 200;

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            $overTolerance,
            '254712345678'
        );

        $initialPaymentCount = Payment::count();

        $response = $this->postJson($this->stkRoute, $payload);

        $response->assertOk();
        $this->assertEquals($initialPaymentCount + 1, Payment::count());
        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
            'landlord_id' => $this->landlord->id,
        ]);

        $payment = Payment::latest('id')->first();
        $this->assertStringContainsString('NEEDS RECONCILIATION', $payment->notes);
    }

    public function test_stk_callback_flags_underpayment_beyond_tolerance(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        $this->createStkPaymentLink($checkoutRequestId);
        $underTolerance = (float) $this->invoice->total_due - 200;

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            $underTolerance,
            '254712345678'
        );

        $initialPaymentCount = Payment::count();

        $response = $this->postJson($this->stkRoute, $payload);

        $response->assertOk();
        $this->assertEquals($initialPaymentCount + 1, Payment::count());
        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
        ]);

        $payment = Payment::latest('id')->first();
        $this->assertStringContainsString('NEEDS RECONCILIATION', $payment->notes);
    }

    public function test_stk_mismatch_records_payment_and_flags_reconciliation(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        $this->createStkPaymentLink($checkoutRequestId);
        $mismatchAmount = (float) $this->invoice->total_due + 500;

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            $mismatchAmount,
            '254712345678'
        );

        $this->postJson($this->stkRoute, $payload);

        $receiptNumber = $payload['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
        $idempotencyKey = IdempotencyKey::where('key', "mpesa:{$receiptNumber}")->first();

        $this->assertNotNull($idempotencyKey);
        $this->assertEquals('completed', $idempotencyKey->status);

        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
        ]);
    }

    public function test_c2b_accepts_partial_payment_without_validation(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);
        $partialAmount = $this->invoice->total_due / 2;

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => (string) $partialAmount,
            'BusinessShortCode' => '174379',
            'BillRefNumber' => $this->invoice->invoice_number,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '0',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'MiddleName' => '',
            'LastName' => 'User',
        ];

        $initialPaymentCount = Payment::count();

        $response = $this->postJson($this->c2bRoute, $payload);

        $response->assertOk();
        $this->assertGreaterThan($initialPaymentCount, Payment::count());
        $this->assertDatabaseMissing('webhook_dead_letters', [
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'error_class' => WebhookDeadLetter::ERROR_SCHEMA,
        ]);

        $this->invoice->refresh();
        $this->assertEquals('partial', $this->invoice->status->value);
    }
}
