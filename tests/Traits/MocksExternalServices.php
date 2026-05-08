<?php

namespace Tests\Traits;

use App\Services\DashboardService;
use App\Services\MpesaService;
use App\Services\PaystackService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Http;
use Mockery;

trait MocksExternalServices
{
    protected function mockMpesaService(?array $stkResponse = null): void
    {
        config(['mpesa.allowed_ips' => []]);

        $mock = Mockery::mock(MpesaService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);

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

    protected function getMockMpesaStkSuccessCallback(string $checkoutRequestId, float $amount, string $phone): array
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

    protected function mockWhatsAppSend(bool $success = true): void
    {
        Http::fake([
            'api.twilio.com/*/Messages.json' => $success
                ? Http::response(['sid' => 'SM'.uniqid()], 201)
                : Http::response(['message' => 'Invalid phone number'], 400),
        ]);
    }

    protected function mockSmsSend(bool $success = true): void
    {
        Http::fake([
            'api.africastalking.com/*' => $success
                ? Http::response([
                    'SMSMessageData' => [
                        'Recipients' => [['status' => 'Success', 'messageId' => 'ATX'.uniqid()]],
                    ],
                ], 200)
                : Http::response(['message' => 'Failed'], 400),
        ]);
    }

    protected function mockPushNotificationService(bool $configured = true, bool $hasSubscriptions = true): void
    {
        $mock = Mockery::mock(PushNotificationService::class);
        $mock->shouldReceive('isConfigured')->andReturn($configured);
        $mock->shouldReceive('getUserSubscriptions')->andReturn(
            $hasSubscriptions ? collect([['endpoint' => 'https://push.example.com']]) : collect([])
        );
        $mock->shouldReceive('send')->andReturn(true);
        $this->app->instance(PushNotificationService::class, $mock);
    }

    protected function mockDashboardService(array $metrics = []): void
    {
        $defaultMetrics = [
            'monthly_revenue' => 150000,
            'collection_rate' => 85.5,
            'total_arrears' => 25000,
            'occupied_units' => 8,
            'vacant_units' => 2,
        ];

        $mock = Mockery::mock(DashboardService::class);
        $mock->shouldReceive('calculateQuickMetrics')->andReturn(array_merge($defaultMetrics, $metrics));
        $this->app->instance(DashboardService::class, $mock);
    }
}
