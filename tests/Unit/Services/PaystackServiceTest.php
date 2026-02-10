<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaystackService $service;

    protected PaymentConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->config = PaymentConfiguration::factory()->forLandlord($landlord)->withPaystack()->create([
            'paystack_public_key' => 'pk_test_xxxxxxxxxxxxx',
            'paystack_secret_key' => 'sk_test_xxxxxxxxxxxxx',
        ]);
        $this->service = new PaystackService($this->config);
    }

    public function test_generates_unique_reference(): void
    {
        $reference = PaystackService::generateReference();

        $this->assertStringStartsWith('PAY-', $reference);
        $this->assertMatchesRegularExpression('/^PAY-\d+-[A-Z0-9]{6}$/', $reference);
    }

    public function test_generates_reference_with_custom_prefix(): void
    {
        $reference = PaystackService::generateReference('RENT');

        $this->assertStringStartsWith('RENT-', $reference);
        $this->assertMatchesRegularExpression('/^RENT-\d+-[A-Z0-9]{6}$/', $reference);
    }

    public function test_reference_uniqueness(): void
    {
        $references = [];
        for ($i = 0; $i < 100; $i++) {
            $references[] = PaystackService::generateReference();
        }

        $this->assertCount(100, array_unique($references));
    }

    public function test_verifies_webhook_signature(): void
    {
        $payload = '{"event":"charge.success","data":{"reference":"PAY-123"}}';
        $expectedSignature = hash_hmac('sha512', $payload, 'sk_test_xxxxxxxxxxxxx');

        $this->assertTrue($this->service->verifyWebhookSignature($payload, $expectedSignature));
    }

    public function test_rejects_invalid_webhook_signature(): void
    {
        $payload = '{"event":"charge.success","data":{"reference":"PAY-123"}}';
        $invalidSignature = 'invalid_signature_hash';

        $this->assertFalse($this->service->verifyWebhookSignature($payload, $invalidSignature));
    }

    public function test_rejects_tampered_payload(): void
    {
        $originalPayload = '{"event":"charge.success","data":{"reference":"PAY-123","amount":1000}}';
        $signature = hash_hmac('sha512', $originalPayload, 'sk_test_xxxxxxxxxxxxx');

        $tamperedPayload = '{"event":"charge.success","data":{"reference":"PAY-123","amount":5000}}';

        $this->assertFalse($this->service->verifyWebhookSignature($tamperedPayload, $signature));
    }

    public function test_get_public_key(): void
    {
        $publicKey = $this->service->getPublicKey();

        $this->assertEquals('pk_test_xxxxxxxxxxxxx', $publicKey);
    }

    public function test_get_secret_key(): void
    {
        $secretKey = $this->service->getSecretKey();

        $this->assertEquals('sk_test_xxxxxxxxxxxxx', $secretKey);
    }

    public function test_signature_verification_timing_safe(): void
    {
        $payload = '{"event":"charge.success"}';
        $correctSignature = hash_hmac('sha512', $payload, 'sk_test_xxxxxxxxxxxxx');

        $almostCorrectSignature = substr($correctSignature, 0, -1).'X';

        $this->assertTrue($this->service->verifyWebhookSignature($payload, $correctSignature));
        $this->assertFalse($this->service->verifyWebhookSignature($payload, $almostCorrectSignature));
    }

    public function test_can_construct_without_config(): void
    {
        $service = new PaystackService(null);

        $this->assertFalse($service->isConfigured());
    }

    public function test_throws_exception_when_using_service_without_config(): void
    {
        $service = new PaystackService(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PaystackService requires Paystack credentials');

        $service->initializeTransaction([
            'email' => 'test@example.com',
            'amount' => 1000,
            'reference' => 'TEST-123',
        ]);
    }

    public function test_reads_timeout_from_config(): void
    {
        config(['payments.gateways.paystack.timeout_seconds' => 45]);

        $method = new \ReflectionMethod(PaystackService::class, 'timeoutSeconds');
        $method->setAccessible(true);

        $this->assertEquals(45, $method->invoke($this->service));
    }

    public function test_reads_retry_attempts_from_config(): void
    {
        config(['payments.gateways.paystack.retry_attempts' => 7]);

        $method = new \ReflectionMethod(PaystackService::class, 'retryAttempts');
        $method->setAccessible(true);

        $this->assertEquals(7, $method->invoke($this->service));
    }

    public function test_reads_retry_delay_from_config(): void
    {
        config(['payments.gateways.paystack.retry_delay_ms' => 500]);

        $method = new \ReflectionMethod(PaystackService::class, 'retryDelayMs');
        $method->setAccessible(true);

        $this->assertEquals(500, $method->invoke($this->service));
    }

    public function test_falls_back_to_defaults_when_config_missing(): void
    {
        config(['payments.gateways.paystack' => null]);

        $timeout = new \ReflectionMethod(PaystackService::class, 'timeoutSeconds');
        $timeout->setAccessible(true);

        $retry = new \ReflectionMethod(PaystackService::class, 'retryAttempts');
        $retry->setAccessible(true);

        $delay = new \ReflectionMethod(PaystackService::class, 'retryDelayMs');
        $delay->setAccessible(true);

        $this->assertEquals(30, $timeout->invoke($this->service));
        $this->assertEquals(3, $retry->invoke($this->service));
        $this->assertEquals(100, $delay->invoke($this->service));
    }
}
