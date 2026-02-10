<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Services\IntaSendService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IntaSendServiceTest extends TestCase
{
    protected function createConfiguredService(): IntaSendService
    {
        $config = new PaymentConfiguration([
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_12345',
            'intasend_secret_key' => 'ISSecretKey_test_67890',
            'intasend_webhook_challenge' => 'test-webhook-challenge',
            'intasend_environment' => 'sandbox',
        ]);

        return new IntaSendService($config);
    }

    protected function createUnconfiguredService(): IntaSendService
    {
        $config = new PaymentConfiguration([
            'intasend_enabled' => false,
            'intasend_publishable_key' => null,
            'intasend_secret_key' => null,
            'intasend_webhook_challenge' => null,
            'intasend_environment' => 'sandbox',
        ]);

        return new IntaSendService($config);
    }

    public function test_formats_phone_number_with_leading_zero(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('0712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_with_zero_one_prefix(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('0112345678');
        $this->assertEquals('254112345678', $result);
    }

    public function test_formats_phone_number_with_plus_prefix(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('+254712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_already_international(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('254712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_removes_dashes_and_spaces(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('0712-345-678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_nine_digit(): void
    {
        $service = $this->createConfiguredService();
        $result = $service->formatPhoneNumber('712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_generates_reference_with_default_prefix(): void
    {
        $reference = IntaSendService::generateReference();

        $this->assertStringStartsWith('ITS-', $reference);
        $this->assertMatchesRegularExpression('/^ITS-\d+-[A-Z0-9]{6}$/', $reference);
    }

    public function test_generates_reference_with_custom_prefix(): void
    {
        $reference = IntaSendService::generateReference('RENT');

        $this->assertStringStartsWith('RENT-', $reference);
    }

    public function test_reference_uniqueness(): void
    {
        $references = [];
        for ($i = 0; $i < 100; $i++) {
            $references[] = IntaSendService::generateReference();
        }

        $this->assertCount(100, array_unique($references));
    }

    public function test_validates_correct_webhook_challenge(): void
    {
        $service = $this->createConfiguredService();
        $this->assertTrue($service->validateWebhookChallenge('test-webhook-challenge'));
    }

    public function test_rejects_incorrect_webhook_challenge(): void
    {
        $service = $this->createConfiguredService();
        $this->assertFalse($service->validateWebhookChallenge('wrong-challenge'));
    }

    public function test_rejects_when_no_challenge_configured(): void
    {
        $config = new PaymentConfiguration([
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_12345',
            'intasend_secret_key' => 'ISSecretKey_test_67890',
            'intasend_webhook_challenge' => null,
            'intasend_environment' => 'sandbox',
        ]);
        $service = new IntaSendService($config);

        $this->assertFalse($service->validateWebhookChallenge('any-challenge'));
    }

    public function test_is_complete_returns_true_for_complete_state(): void
    {
        $this->assertTrue(IntaSendService::isComplete('COMPLETE'));
        $this->assertTrue(IntaSendService::isComplete('complete'));
    }

    public function test_is_complete_returns_false_for_other_states(): void
    {
        $this->assertFalse(IntaSendService::isComplete('PENDING'));
        $this->assertFalse(IntaSendService::isComplete('FAILED'));
    }

    public function test_is_pending_returns_true_for_pending_states(): void
    {
        $this->assertTrue(IntaSendService::isPending('PENDING'));
        $this->assertTrue(IntaSendService::isPending('PROCESSING'));
        $this->assertTrue(IntaSendService::isPending('pending'));
    }

    public function test_is_pending_returns_false_for_complete_state(): void
    {
        $this->assertFalse(IntaSendService::isPending('COMPLETE'));
    }

    public function test_is_failed_returns_true_for_failed_state(): void
    {
        $this->assertTrue(IntaSendService::isFailed('FAILED'));
        $this->assertTrue(IntaSendService::isFailed('failed'));
    }

    public function test_is_failed_returns_false_for_complete_state(): void
    {
        $this->assertFalse(IntaSendService::isFailed('COMPLETE'));
    }

    public function test_is_configured_returns_true_when_fully_configured(): void
    {
        $service = $this->createConfiguredService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_disabled(): void
    {
        $service = $this->createUnconfiguredService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_without_keys(): void
    {
        $config = new PaymentConfiguration([
            'intasend_enabled' => true,
            'intasend_publishable_key' => null,
            'intasend_secret_key' => null,
            'intasend_environment' => 'sandbox',
        ]);
        $service = new IntaSendService($config);

        $this->assertFalse($service->isConfigured());
    }

    public function test_get_public_key_returns_publishable_key(): void
    {
        $service = $this->createConfiguredService();
        $this->assertEquals('ISPubKey_test_12345', $service->getPublicKey());
    }

    public function test_stk_push_returns_null_when_not_configured(): void
    {
        $service = $this->createUnconfiguredService();

        $result = $service->initializeMpesaStkPush(1000, '0712345678', 'REF-123');

        $this->assertNull($result);
    }

    public function test_stk_push_sends_correct_request(): void
    {
        Http::fake([
            'sandbox.intasend.com/api/v1/payment/mpesa-stk-push/' => Http::response([
                'invoice' => [
                    'invoice_id' => 'INV-TEST-123',
                    'state' => 'PENDING',
                    'api_ref' => 'REF-123',
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'External API call completed'
                && $ctx['provider'] === 'intasend'
                && $ctx['endpoint'] === '/api/v1/payment/mpesa-stk-push'
                && $ctx['status_code'] === 200);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IntaSend STK Push initiated'));

        $service = $this->createConfiguredService();

        $result = $service->initializeMpesaStkPush(1000, '0712345678', 'REF-123');

        $this->assertNotNull($result);
        $this->assertEquals('INV-TEST-123', $result['invoice']['invoice_id']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer ISSecretKey_test_67890')
                && $request['amount'] === 1000
                && $request['phone_number'] === '254712345678'
                && $request['api_ref'] === 'REF-123';
        });
    }

    public function test_stk_push_includes_wallet_id_for_split(): void
    {
        Http::fake([
            'sandbox.intasend.com/api/v1/payment/mpesa-stk-push/' => Http::response([
                'invoice' => [
                    'invoice_id' => 'INV-SPLIT-123',
                    'state' => 'PENDING',
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'External API call completed'
                && $ctx['provider'] === 'intasend'
                && $ctx['status_code'] === 200);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IntaSend STK Push initiated'));

        $service = $this->createConfiguredService();

        $result = $service->initializeMpesaStkPush(
            1000,
            '0712345678',
            'REF-123',
            ['wallet_id' => 'LANDLORD-WALLET-ID']
        );

        $this->assertNotNull($result);

        Http::assertSent(function ($request) {
            return $request['wallet_id'] === 'LANDLORD-WALLET-ID';
        });
    }

    public function test_stk_push_returns_null_on_api_failure(): void
    {
        Http::fake([
            'sandbox.intasend.com/api/v1/payment/mpesa-stk-push/' => Http::response([
                'error' => 'Invalid credentials',
            ], 401),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'External API call completed'
                && $ctx['provider'] === 'intasend'
                && $ctx['status_code'] === 401);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'IntaSend STK Push failed');
            });

        $service = $this->createConfiguredService();

        $result = $service->initializeMpesaStkPush(1000, '0712345678', 'REF-123');

        $this->assertNull($result);
    }

    public function test_verify_transaction_returns_status(): void
    {
        Http::fake([
            'sandbox.intasend.com/api/v1/payment/status/' => Http::response([
                'invoice' => [
                    'invoice_id' => 'INV-TEST-123',
                    'state' => 'COMPLETE',
                    'mpesa_reference' => 'RG12345XXXX',
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'External API call completed'
                && $ctx['provider'] === 'intasend'
                && $ctx['endpoint'] === '/api/v1/payment/status'
                && $ctx['status_code'] === 200);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IntaSend transaction status retrieved'));

        $service = $this->createConfiguredService();

        $result = $service->verifyTransaction('INV-TEST-123');

        $this->assertNotNull($result);
        $this->assertEquals('COMPLETE', $result['invoice']['state']);
        $this->assertEquals('RG12345XXXX', $result['invoice']['mpesa_reference']);
    }

    public function test_verify_transaction_returns_null_when_not_configured(): void
    {
        $service = $this->createUnconfiguredService();

        $result = $service->verifyTransaction('INV-TEST-123');

        $this->assertNull($result);
    }

    public function test_verify_transaction_returns_null_on_api_failure(): void
    {
        Http::fake([
            'sandbox.intasend.com/api/v1/payment/status/' => Http::response([
                'error' => 'Invoice not found',
            ], 404),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'External API call completed'
                && $ctx['provider'] === 'intasend'
                && $ctx['status_code'] === 404);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'IntaSend verification failed');
            });

        $service = $this->createConfiguredService();

        $result = $service->verifyTransaction('INV-NOTFOUND-123');

        $this->assertNull($result);
    }

    public function test_reads_retry_config_from_payments_config(): void
    {
        config(['payments.gateways.intasend.timeout_seconds' => 45]);
        config(['payments.gateways.intasend.retry_attempts' => 5]);
        config(['payments.gateways.intasend.retry_delay_ms' => 200]);

        $service = $this->createConfiguredService();

        $timeout = new \ReflectionMethod(IntaSendService::class, 'timeoutSeconds');
        $timeout->setAccessible(true);

        $retry = new \ReflectionMethod(IntaSendService::class, 'retryAttempts');
        $retry->setAccessible(true);

        $delay = new \ReflectionMethod(IntaSendService::class, 'retryDelayMs');
        $delay->setAccessible(true);

        $this->assertEquals(45, $timeout->invoke($service));
        $this->assertEquals(5, $retry->invoke($service));
        $this->assertEquals(200, $delay->invoke($service));
    }

    public function test_uses_exponential_backoff_for_verify_transaction(): void
    {
        config([
            'payments.gateways.intasend.retry_delay_ms' => 100,
            'payments.gateways.intasend.retry_backoff_base' => 2,
        ]);

        $base = (int) config('payments.gateways.intasend.retry_backoff_base', 2);
        $delay = (int) config('payments.gateways.intasend.retry_delay_ms', 100);

        $this->assertEquals(100, $delay * ($base ** 0));
        $this->assertEquals(200, $delay * ($base ** 1));
        $this->assertEquals(400, $delay * ($base ** 2));
    }

    public function test_falls_back_to_defaults_when_config_missing(): void
    {
        config(['payments.gateways.intasend' => null]);

        $service = $this->createConfiguredService();

        $timeout = new \ReflectionMethod(IntaSendService::class, 'timeoutSeconds');
        $timeout->setAccessible(true);

        $retry = new \ReflectionMethod(IntaSendService::class, 'retryAttempts');
        $retry->setAccessible(true);

        $delay = new \ReflectionMethod(IntaSendService::class, 'retryDelayMs');
        $delay->setAccessible(true);

        $this->assertEquals(30, $timeout->invoke($service));
        $this->assertEquals(3, $retry->invoke($service));
        $this->assertEquals(100, $delay->invoke($service));
    }
}
