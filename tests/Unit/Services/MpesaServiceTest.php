<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MpesaService $service;

    protected PaymentConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->config = PaymentConfiguration::factory()->forLandlord($landlord)->withMpesa()->create([
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);
        $this->service = new MpesaService($this->config);
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

        $this->assertTrue($this->service->validateWebhookIP('196.201.214.200'));
        $this->assertTrue($this->service->validateWebhookIP('196.201.214.206'));
        $this->assertFalse($this->service->validateWebhookIP('192.168.1.1'));
    }

    public function test_validates_webhook_ip_empty_whitelist(): void
    {
        config(['mpesa.allowed_ips' => []]);

        $this->assertTrue($this->service->validateWebhookIP('192.168.1.1'));
        $this->assertTrue($this->service->validateWebhookIP('any.ip.address.here'));
    }

    public function test_can_construct_without_config(): void
    {
        $service = new MpesaService(null);

        $this->assertFalse($service->isConfigured());
    }

    public function test_throws_exception_when_using_service_without_config(): void
    {
        $service = new MpesaService(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MpesaService requires M-Pesa credentials');

        $service->getAccessToken();
    }

    public function test_throws_exception_when_with_config_without_api_credentials(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $configWithoutCreds = PaymentConfiguration::factory()
            ->forLandlord($landlord)
            ->withMpesa()
            ->create([
                'mpesa_consumer_key' => null,
                'mpesa_consumer_secret' => null,
            ]);

        $service = new MpesaService(null);

        $this->expectException(\InvalidArgumentException::class);
        $service->withConfig($configWithoutCreds);
    }

    public function test_checkout_reference_uniqueness(): void
    {
        $references = [];
        for ($i = 0; $i < 100; $i++) {
            $references[] = MpesaService::generateCheckoutReference();
        }

        $this->assertCount(100, array_unique($references));
    }

    public function test_reads_retry_config_from_payments_config(): void
    {
        config(['payments.gateways.mpesa.timeout_seconds' => 60]);
        config(['payments.gateways.mpesa.retry_attempts' => 10]);
        config(['payments.gateways.mpesa.retry_delay_ms' => 250]);

        $timeout = new \ReflectionMethod(MpesaService::class, 'timeoutSeconds');
        $timeout->setAccessible(true);

        $retry = new \ReflectionMethod(MpesaService::class, 'retryAttempts');
        $retry->setAccessible(true);

        $delay = new \ReflectionMethod(MpesaService::class, 'retryDelayMs');
        $delay->setAccessible(true);

        $this->assertEquals(60, $timeout->invoke($this->service));
        $this->assertEquals(10, $retry->invoke($this->service));
        $this->assertEquals(250, $delay->invoke($this->service));
    }

    public function test_uses_exponential_backoff(): void
    {
        config([
            'payments.gateways.mpesa.retry_delay_ms' => 100,
            'payments.gateways.mpesa.retry_backoff_base' => 2,
        ]);

        $base = (int) config('payments.gateways.mpesa.retry_backoff_base', 2);
        $delay = (int) config('payments.gateways.mpesa.retry_delay_ms', 100);

        $this->assertEquals(100, $delay * ($base ** 0));
        $this->assertEquals(200, $delay * ($base ** 1));
        $this->assertEquals(400, $delay * ($base ** 2));
        $this->assertEquals(800, $delay * ($base ** 3));
        $this->assertEquals(1600, $delay * ($base ** 4));
    }

    public function test_falls_back_to_defaults_when_config_missing(): void
    {
        config(['payments.gateways.mpesa' => null]);

        $timeout = new \ReflectionMethod(MpesaService::class, 'timeoutSeconds');
        $timeout->setAccessible(true);

        $retry = new \ReflectionMethod(MpesaService::class, 'retryAttempts');
        $retry->setAccessible(true);

        $delay = new \ReflectionMethod(MpesaService::class, 'retryDelayMs');
        $delay->setAccessible(true);

        $this->assertEquals(30, $timeout->invoke($this->service));
        $this->assertEquals(3, $retry->invoke($this->service));
        $this->assertEquals(100, $delay->invoke($this->service));
    }
}
