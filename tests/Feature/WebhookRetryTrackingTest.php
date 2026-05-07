<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class WebhookRetryTrackingTest extends TestCase
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

    public function test_mpesa_stk_callback_creates_webhook_log(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 0,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'PENDING-STK-'.uniqid(),
            'mpesa_checkout_request_id' => $checkoutRequestId,
        ]);

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            (float) $this->invoice->total_due,
            '254712345678'
        );

        $this->postJson($this->stkRoute, $payload);

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
            'status' => WebhookLog::STATUS_PROCESSED,
        ]);

        $log = WebhookLog::withoutGlobalScope('landlord')->first();
        $this->assertEquals(1, $log->retry_count);
        $this->assertNotNull($log->processing_time_ms);
        $this->assertNotNull($log->payload_hash);
    }

    public function test_mpesa_duplicate_callback_increments_retry_count(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 0,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'PENDING-STK-'.uniqid(),
            'mpesa_checkout_request_id' => $checkoutRequestId,
        ]);

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            (float) $this->invoice->total_due,
            '254712345678'
        );

        $this->postJson($this->stkRoute, $payload);
        $this->postJson($this->stkRoute, $payload);

        $logs = WebhookLog::withoutGlobalScope('landlord')
            ->where('provider', WebhookLog::PROVIDER_MPESA)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertEquals(2, $logs->first()->retry_count);
    }

    public function test_mpesa_failed_stk_callback_creates_webhook_log(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();

        $payload = $this->getMockMpesaStkFailedCallback($checkoutRequestId);

        $this->postJson($this->stkRoute, $payload);

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
        ]);

        $log = WebhookLog::withoutGlobalScope('landlord')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->processing_time_ms);
    }

    public function test_mpesa_c2b_confirmation_creates_webhook_log(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);
        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => (float) $this->invoice->total_due,
            'BusinessShortCode' => '174379',
            'BillRefNumber' => $this->invoice->invoice_number,
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
        ];

        $this->postJson($this->c2bRoute, $payload);

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_id' => $transactionId,
            'event_type' => 'c2b_confirmation',
        ]);
    }

    public function test_high_retry_scope_returns_correct_entries(): void
    {
        WebhookLog::withoutGlobalScope('landlord')->insert([
            [
                'provider' => WebhookLog::PROVIDER_MPESA,
                'event_id' => 'LOW-RETRY-1',
                'event_type' => 'stk_callback',
                'payload_hash' => hash('sha256', 'test1'),
                'retry_count' => 1,
                'first_received_at' => now(),
                'last_received_at' => now(),
                'status' => WebhookLog::STATUS_PROCESSED,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => WebhookLog::PROVIDER_INTASEND,
                'event_id' => 'HIGH-RETRY-1',
                'event_type' => 'payment.complete',
                'payload_hash' => hash('sha256', 'test2'),
                'retry_count' => 5,
                'first_received_at' => now()->subMinutes(25),
                'last_received_at' => now(),
                'status' => WebhookLog::STATUS_PROCESSED,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => WebhookLog::PROVIDER_MPESA,
                'event_id' => 'HIGH-RETRY-2',
                'event_type' => 'c2b_confirmation',
                'payload_hash' => hash('sha256', 'test3'),
                'retry_count' => 3,
                'first_received_at' => now()->subMinutes(15),
                'last_received_at' => now(),
                'status' => WebhookLog::STATUS_FAILED,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $highRetry = WebhookLog::withoutGlobalScope('landlord')->highRetry(3)->get();

        $this->assertCount(2, $highRetry);
        $this->assertTrue($highRetry->every(fn ($log) => $log->retry_count >= 3));
    }

    public function test_webhook_log_processing_time_is_recorded(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 0,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'PENDING-STK-'.uniqid(),
            'mpesa_checkout_request_id' => $checkoutRequestId,
        ]);

        $payload = $this->getMockMpesaStkSuccessCallback(
            $checkoutRequestId,
            (float) $this->invoice->total_due,
            '254712345678'
        );

        $this->postJson($this->stkRoute, $payload);

        $log = WebhookLog::withoutGlobalScope('landlord')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->processing_time_ms);
        $this->assertGreaterThanOrEqual(0, $log->processing_time_ms);
    }
}
