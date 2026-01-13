<?php

namespace Tests\Traits;

use App\Services\MpesaService;
use App\Services\PaystackService;
use Mockery;

trait MocksExternalServices
{
    protected function mockMpesaService(?array $stkResponse = null): void
    {
        $mock = Mockery::mock(MpesaService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('validateWebhookIP')->andReturn(true);

        if ($stkResponse) {
            $mock->shouldReceive('initiateSTKPush')->andReturn($stkResponse);
        }

        $this->app->instance(MpesaService::class, $mock);
    }

    protected function mockPaystackService(?array $initResponse = null, ?array $verifyResponse = null): void
    {
        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);

        if ($initResponse) {
            $mock->shouldReceive('initializeTransaction')->andReturn($initResponse);
        }

        if ($verifyResponse) {
            $mock->shouldReceive('verifyTransaction')->andReturn($verifyResponse);
        }

        $this->app->instance(PaystackService::class, $mock);
    }

    protected function getMockMpesaStkSuccessCallback(string $checkoutRequestId, int $amount, string $phone): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => $amount],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QKL'.rand(100000000, 999999999)],
                            ['Name' => 'PhoneNumber', 'Value' => $phone],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getMockMpesaStkFailedCallback(string $checkoutRequestId): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ];
    }

    protected function getMockPaystackWebhookPayload(string $reference, int $amount, string $event = 'charge.success'): array
    {
        return [
            'event' => $event,
            'data' => [
                'reference' => $reference,
                'amount' => $amount * 100,
                'status' => 'success',
                'channel' => 'card',
                'currency' => 'KES',
                'paid_at' => now()->toIso8601String(),
                'metadata' => [
                    'invoice_id' => 1,
                    'lease_id' => 1,
                ],
            ],
        ];
    }
}
