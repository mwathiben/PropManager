<?php

namespace Tests\Unit\Services;

use App\Services\MpesaService;
use Tests\TestCase;

class MpesaServiceTest extends TestCase
{
    protected MpesaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MpesaService;
    }

    public function test_formats_phone_number_with_leading_zero(): void
    {
        $method = new \ReflectionMethod(MpesaService::class, 'formatPhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '0712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_with_country_code(): void
    {
        $method = new \ReflectionMethod(MpesaService::class, 'formatPhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '254712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_with_plus_prefix(): void
    {
        $method = new \ReflectionMethod(MpesaService::class, 'formatPhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '+254712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_removes_non_numeric(): void
    {
        $method = new \ReflectionMethod(MpesaService::class, 'formatPhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '0712-345-678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_formats_phone_number_without_country_code(): void
    {
        $method = new \ReflectionMethod(MpesaService::class, 'formatPhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '712345678');
        $this->assertEquals('254712345678', $result);
    }

    public function test_generates_account_reference(): void
    {
        $reference = $this->service->generateAccountReference();

        $this->assertStringStartsWith('PROP-', $reference);
        $this->assertMatchesRegularExpression('/^PROP-\d+-[a-z0-9]{4}$/i', $reference);
    }

    public function test_generates_account_reference_with_custom_prefix(): void
    {
        $reference = $this->service->generateAccountReference('RENT');

        $this->assertStringStartsWith('RENT-', $reference);
    }

    public function test_generates_checkout_reference(): void
    {
        $reference = MpesaService::generateCheckoutReference();

        $this->assertStringStartsWith('MPESA-', $reference);
        $this->assertMatchesRegularExpression('/^MPESA-\d+-[A-Z0-9]{6}$/', $reference);
    }

    public function test_validates_webhook_ip_in_whitelist(): void
    {
        config(['mpesa.allowed_ips' => ['196.201.214.200', '196.201.214.206']]);

        $service = new MpesaService;

        $this->assertTrue($service->validateWebhookIP('196.201.214.200'));
        $this->assertTrue($service->validateWebhookIP('196.201.214.206'));
        $this->assertFalse($service->validateWebhookIP('192.168.1.1'));
    }

    public function test_validates_webhook_ip_empty_whitelist(): void
    {
        config(['mpesa.allowed_ips' => []]);

        $service = new MpesaService;

        $this->assertTrue($service->validateWebhookIP('192.168.1.1'));
        $this->assertTrue($service->validateWebhookIP('any.ip.address.here'));
    }

    public function test_is_configured_returns_false_without_credentials(): void
    {
        config([
            'mpesa.consumer_key' => '',
            'mpesa.consumer_secret' => '',
            'mpesa.stk.shortcode' => '',
        ]);

        $service = new MpesaService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_with_credentials(): void
    {
        config([
            'mpesa.consumer_key' => 'test_consumer_key',
            'mpesa.consumer_secret' => 'test_consumer_secret',
            'mpesa.stk.shortcode' => '174379',
        ]);

        $service = new MpesaService;

        $this->assertTrue($service->isConfigured());
    }

    public function test_checkout_reference_uniqueness(): void
    {
        $references = [];
        for ($i = 0; $i < 100; $i++) {
            $references[] = MpesaService::generateCheckoutReference();
        }

        $this->assertCount(100, array_unique($references));
    }
}
