<?php

namespace Tests\Unit\Jobs;

use App\Contracts\SmsServiceInterface;
use App\Jobs\ProcessQueuedPaymentIntents;
use App\Models\PaymentConfiguration;
use App\Models\QueuedPaymentIntent;
use App\Services\IntaSendService;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksExternalServices;

class ProcessQueuedPaymentIntentsTest extends TestCase
{
    use MocksExternalServices, RefreshDatabase;

    private $smsMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->smsMock = Mockery::mock(SmsServiceInterface::class);
        $this->app->instance(SmsServiceInterface::class, $this->smsMock);
    }

    public function test_marks_expired_intents(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'expires_at' => now()->subHour(),
        ]);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_EXPIRED, $intent->status);
    }

    public function test_does_not_process_terminal_intents(): void
    {
        $this->smsMock->shouldReceive('send')->never();

        $completed = QueuedPaymentIntent::factory()->completed()->create([
            'payment_method' => 'mpesa',
        ]);
        $failed = QueuedPaymentIntent::factory()->failed()->create([
            'payment_method' => 'mpesa',
        ]);
        $expired = QueuedPaymentIntent::factory()->expired()->create([
            'payment_method' => 'mpesa',
        ]);

        $this->runJob();

        $this->assertEquals(QueuedPaymentIntent::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $failed->refresh()->status);
        $this->assertEquals(QueuedPaymentIntent::STATUS_EXPIRED, $expired->refresh()->status);
    }

    public function test_processes_retryable_mpesa_intent(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_test_123',
                'MerchantRequestID' => 'MR_test_123',
            ]);
        $this->app->instance(MpesaService::class, $mpesaMock);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PROCESSING, $intent->status);
        $this->assertEquals(1, $intent->attempts);
    }

    public function test_processes_retryable_intasend_intent(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'intasend',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
            'intasend_publishable_key' => 'ISPubKey_test',
            'intasend_secret_key' => 'ISSecretKey_test',
            'intasend_environment' => 'sandbox',
        ]);

        $intaSendMock = Mockery::mock(IntaSendService::class);
        $intaSendMock->shouldReceive('formatPhoneNumber')
            ->andReturn('254712345678');
        $intaSendMock->shouldReceive('initializeMpesaStkPush')
            ->once()
            ->andReturn([
                'invoice' => ['invoice_id' => 'INV_test_123', 'state' => 'PENDING'],
            ]);

        $this->app->bind(IntaSendService::class, function () use ($intaSendMock) {
            return $intaSendMock;
        });

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PROCESSING, $intent->status);
        $this->assertDatabaseHas('intasend_transactions', [
            'landlord_id' => $intent->landlord_id,
            'invoice_id' => $intent->invoice_id,
        ]);
    }

    public function test_intasend_fails_when_invoice_id_is_null(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'intasend',
            'invoice_id' => null,
            'landlord_id' => $landlord->id,
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord->id,
            'intasend_publishable_key' => 'ISPubKey_test',
            'intasend_secret_key' => 'ISSecretKey_test',
        ]);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $intent->status);
        $this->assertStringContainsString('invoice reference', $intent->failure_reason);
    }

    public function test_marks_failed_when_max_attempts_exceeded(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $maxAttempts = config('payments.queued_intents.max_attempts', 3);

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'attempts' => $maxAttempts,
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
        ]);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $intent->status);
        $this->assertStringContainsString('Maximum retry attempts', $intent->failure_reason);
    }

    public function test_handles_gateway_null_response(): void
    {
        $this->smsMock->shouldReceive('send')->never();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->once()
            ->andReturn(null);
        $this->app->instance(MpesaService::class, $mpesaMock);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PENDING, $intent->status);
    }

    public function test_handles_gateway_exception_gracefully(): void
    {
        $this->smsMock->shouldReceive('send')->never();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));
        $this->app->instance(MpesaService::class, $mpesaMock);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PENDING, $intent->status);
        $this->assertGreaterThanOrEqual(1, $intent->attempts);
    }

    public function test_unsupported_payment_method_marked_failed(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'paystack',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
        ]);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $intent->status);
        $this->assertStringContainsString('not supported', $intent->failure_reason);
    }

    public function test_sends_sms_on_expiry(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'expires_at' => now()->subHour(),
            'phone_number' => '254712345678',
        ]);

        $this->smsMock->shouldReceive('send')
            ->once()
            ->withArgs(function (int $landlordId, string $phone, string $message) use ($intent) {
                return $landlordId === $intent->landlord_id
                    && $phone === '254712345678'
                    && str_contains($message, 'expired');
            })
            ->andReturn(['success' => true, 'message_id' => 'ATX1', 'error' => null]);

        $this->runJob();
    }

    public function test_sends_sms_on_max_attempts_failure(): void
    {
        $maxAttempts = config('payments.queued_intents.max_attempts', 3);

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'attempts' => $maxAttempts,
            'phone_number' => '254712345678',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
        ]);

        $this->smsMock->shouldReceive('send')
            ->once()
            ->withArgs(function (int $landlordId, string $phone, string $message) {
                return str_contains($message, 'failed');
            })
            ->andReturn(['success' => true, 'message_id' => 'ATX2', 'error' => null]);

        $this->runJob();
    }

    public function test_sends_sms_on_initiation(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'phone_number' => '254712345678',
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $intent->landlord_id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->andReturn(['CheckoutRequestID' => 'ws_CO_sms_test']);
        $this->app->instance(MpesaService::class, $mpesaMock);

        $this->smsMock->shouldReceive('send')
            ->once()
            ->withArgs(function (int $landlordId, string $phone, string $message) {
                return str_contains($message, 'initiated') || str_contains($message, 'payment prompt');
            })
            ->andReturn(['success' => true, 'message_id' => 'ATX3', 'error' => null]);

        $this->runJob();
    }

    public function test_sms_failure_does_not_block_processing(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'expires_at' => now()->subHour(),
        ]);

        $this->smsMock->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMS service down'));

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_EXPIRED, $intent->status);
    }

    public function test_skips_intent_when_payment_config_missing(): void
    {
        $this->smsMock->shouldReceive('send')->once();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
        ]);

        PaymentConfiguration::where('landlord_id', $intent->landlord_id)->delete();

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $intent->status);
        $this->assertStringContainsString('not configured', $intent->failure_reason);
    }

    public function test_processes_intents_in_chunks(): void
    {
        $this->smsMock->shouldReceive('send');

        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord->id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->andReturn(['CheckoutRequestID' => 'ws_CO_chunk']);
        $this->app->instance(MpesaService::class, $mpesaMock);

        $invoice = \App\Models\Invoice::factory()->sent()->create(['landlord_id' => $landlord->id]);

        for ($i = 0; $i < 55; $i++) {
            $tenant = \App\Models\User::factory()->create([
                'role' => 'tenant',
                'landlord_id' => $landlord->id,
            ]);
            QueuedPaymentIntent::create([
                'idempotency_key' => QueuedPaymentIntent::generateIdempotencyKey($tenant->id, $invoice->id, uniqid()),
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'landlord_id' => $landlord->id,
                'amount' => 5000,
                'currency' => 'KES',
                'payment_method' => 'mpesa',
                'phone_number' => '254712'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => QueuedPaymentIntent::STATUS_PENDING,
                'attempts' => 0,
                'expires_at' => now()->addHours(24),
            ]);
        }

        $this->runJob();

        $processed = QueuedPaymentIntent::where('status', QueuedPaymentIntent::STATUS_PROCESSING)->count();
        $this->assertEquals(55, $processed);
    }

    public function test_respects_next_retry_at_timing(): void
    {
        $this->smsMock->shouldReceive('send')->never();

        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'payment_method' => 'mpesa',
            'next_retry_at' => now()->addMinutes(5),
        ]);

        $this->runJob();

        $intent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PENDING, $intent->status);
        $this->assertEquals(0, $intent->attempts);
    }

    public function test_recovers_stale_processing_intents(): void
    {
        $this->smsMock->shouldReceive('send');

        $staleIntent = QueuedPaymentIntent::factory()->processing()->create([
            'payment_method' => 'mpesa',
            'last_attempt_at' => now()->subMinutes(15),
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $staleIntent->landlord_id,
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);

        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')
            ->andReturn(['CheckoutRequestID' => 'ws_CO_recovered']);
        $this->app->instance(MpesaService::class, $mpesaMock);

        $this->runJob();

        $staleIntent->refresh();
        $this->assertEquals(QueuedPaymentIntent::STATUS_PROCESSING, $staleIntent->status);
        $this->assertGreaterThanOrEqual(2, $staleIntent->attempts);
    }

    private function runJob(): void
    {
        $job = app(ProcessQueuedPaymentIntents::class);
        $job->handle(
            app(MpesaService::class),
            app(SmsServiceInterface::class),
        );
    }
}
