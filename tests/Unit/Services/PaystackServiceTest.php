<?php

namespace Tests\Unit\Services;

use App\Services\PaystackService;
use Tests\TestCase;

class PaystackServiceTest extends TestCase
{
    protected PaystackService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => 'sk_test_xxxxxxxxxxxxx',
            'services.paystack.public_key' => 'pk_test_xxxxxxxxxxxxx',
        ]);

        $this->service = new PaystackService;
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
        $secretKey = 'sk_test_xxxxxxxxxxxxx';
        config(['services.paystack.secret_key' => $secretKey]);

        $service = new PaystackService;

        $payload = '{"event":"charge.success","data":{"reference":"PAY-123"}}';
        $expectedSignature = hash_hmac('sha512', $payload, $secretKey);

        $this->assertTrue($service->verifyWebhookSignature($payload, $expectedSignature));
    }

    public function test_rejects_invalid_webhook_signature(): void
    {
        $payload = '{"event":"charge.success","data":{"reference":"PAY-123"}}';
        $invalidSignature = 'invalid_signature_hash';

        $this->assertFalse($this->service->verifyWebhookSignature($payload, $invalidSignature));
    }

    public function test_rejects_tampered_payload(): void
    {
        $secretKey = 'sk_test_xxxxxxxxxxxxx';
        config(['services.paystack.secret_key' => $secretKey]);

        $service = new PaystackService;

        $originalPayload = '{"event":"charge.success","data":{"reference":"PAY-123","amount":1000}}';
        $signature = hash_hmac('sha512', $originalPayload, $secretKey);

        $tamperedPayload = '{"event":"charge.success","data":{"reference":"PAY-123","amount":5000}}';

        $this->assertFalse($service->verifyWebhookSignature($tamperedPayload, $signature));
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
        $secretKey = 'sk_test_xxxxxxxxxxxxx';
        config(['services.paystack.secret_key' => $secretKey]);

        $service = new PaystackService;

        $payload = '{"event":"charge.success"}';
        $correctSignature = hash_hmac('sha512', $payload, $secretKey);

        $almostCorrectSignature = substr($correctSignature, 0, -1).'X';

        $this->assertTrue($service->verifyWebhookSignature($payload, $correctSignature));
        $this->assertFalse($service->verifyWebhookSignature($payload, $almostCorrectSignature));
    }
}
