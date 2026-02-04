<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Services\Gateways\MpesaGateway;
use App\Services\MpesaService;
use Mockery;
use Tests\TestCase;

class MpesaGatewayTest extends TestCase
{
    public function test_verify_payment_passes_config_to_service(): void
    {
        $config = new PaymentConfiguration([
            'landlord_id' => 1,
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);

        $mockService = Mockery::mock(MpesaService::class);

        $mockService->shouldReceive('querySTKStatus')
            ->once()
            ->withArgs(function ($reference, $passedConfig) use ($config) {
                return $reference === 'ws_CO_123'
                    && $passedConfig instanceof PaymentConfiguration
                    && $passedConfig->mpesa_consumer_key === $config->mpesa_consumer_key;
            })
            ->andReturn([
                'ResultCode' => '0',
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 1000],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'PK12345678'],
                    ],
                ],
            ]);

        $gateway = new MpesaGateway($mockService);
        $gatewayWithConfig = $gateway->withConfig($config);

        $result = $gatewayWithConfig->verifyPayment('ws_CO_123');

        $this->assertTrue($result->status === 'success');
        $this->assertEquals('PK12345678', $result->transactionId);
    }

    public function test_verify_payment_without_config_returns_failed_result(): void
    {
        $mockService = Mockery::mock(MpesaService::class);

        $gateway = new MpesaGateway($mockService);

        $result = $gateway->verifyPayment('ws_CO_123');

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('PaymentConfiguration required', $result->error);
    }

    public function test_verify_payment_with_config_returns_verified_result(): void
    {
        $config = new PaymentConfiguration([
            'landlord_id' => 1,
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);

        $mockService = Mockery::mock(MpesaService::class);

        $mockService->shouldReceive('querySTKStatus')
            ->once()
            ->with('ws_CO_test', $config)
            ->andReturn([
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 5000],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'PKL567890'],
                    ],
                ],
            ]);

        $gateway = new MpesaGateway($mockService);
        $gatewayWithConfig = $gateway->withConfig($config);

        $result = $gatewayWithConfig->verifyPayment('ws_CO_test');

        $this->assertEquals('success', $result->status);
        $this->assertEquals('PKL567890', $result->transactionId);
        $this->assertEquals(500000, $result->amount->amount);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
