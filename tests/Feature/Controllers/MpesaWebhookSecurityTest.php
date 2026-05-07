<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MpesaWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $stkCallbackApiRoute = '/api/webhooks/mpesa/stk-callback';

    private string $stkCallbackWebRoute = '/webhooks/mpesa/stk-callback';

    private string $safaricomIp = '196.201.214.200';

    private string $unknownIp = '192.168.1.1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mpesa.allowed_ips' => [
            '196.201.214.200',
            '196.201.214.206',
            '196.201.213.114',
        ]]);
    }

    private function makeStkPayload(?string $transactionDate = null): array
    {
        $items = [
            ['Name' => 'Amount', 'Value' => 25000],
            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QKL'.rand(100000000, 999999999)],
            ['Name' => 'PhoneNumber', 'Value' => '254712345678'],
        ];

        if ($transactionDate !== null) {
            $items[] = ['Name' => 'TransactionDate', 'Value' => $transactionDate];
        }

        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => 'ws_CO_'.uniqid(),
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => $items,
                    ],
                ],
            ],
        ];
    }

    private function makeC2bPayload(?string $transTime = null): array
    {
        return array_filter([
            'TransactionType' => 'Pay Bill',
            'TransID' => 'QKL'.rand(100000000, 999999999),
            'TransTime' => $transTime,
            'TransAmount' => '25000.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-202601-0001',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '50000.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
        ], fn ($v) => $v !== null);
    }

    public function test_rejects_request_from_unknown_ip_with_403(): void
    {
        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload(now()->format('YmdHis')),
            ['REMOTE_ADDR' => $this->unknownIp]
        );

        $response->assertStatus(403);
    }

    public function test_allows_request_from_whitelisted_safaricom_ip(): void
    {
        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload(now()->format('YmdHis')),
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(200);
    }

    public function test_rejects_stk_callback_with_stale_timestamp(): void
    {
        $staleTimestamp = now()->subMinutes(20)->format('YmdHis');

        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload($staleTimestamp),
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(403);
    }

    public function test_rejects_c2b_with_stale_transtime(): void
    {
        $staleTimestamp = now()->subMinutes(20)->format('YmdHis');

        $response = $this->postJson(
            '/api/webhooks/mpesa/c2b/confirmation',
            $this->makeC2bPayload($staleTimestamp),
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(403);
    }

    public function test_allows_request_with_recent_timestamp(): void
    {
        $recentTimestamp = now()->subMinutes(5)->format('YmdHis');

        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload($recentTimestamp),
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(200);
    }

    public function test_allows_request_when_no_timestamp_in_payload(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => 'ws_CO_'.uniqid(),
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ];

        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $payload,
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(200);
    }

    public function test_rejects_all_when_whitelist_is_empty(): void
    {
        config(['mpesa.allowed_ips' => []]);

        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload(now()->format('YmdHis')),
            ['REMOTE_ADDR' => $this->unknownIp]
        );

        $response->assertStatus(403);
    }

    public function test_rejects_unknown_ip_in_production_with_empty_whitelist(): void
    {
        config(['mpesa.allowed_ips' => []]);

        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $response = $this->postJson(
                $this->stkCallbackApiRoute,
                $this->makeStkPayload(now()->format('YmdHis')),
                ['REMOTE_ADDR' => $this->unknownIp]
            );

            $response->assertStatus(403);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }

    public function test_rejection_logged_with_structured_context(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'M-Pesa webhook rejected')
                    && $context['provider'] === 'mpesa'
                    && $context['ip'] === $this->unknownIp
                    && isset($context['reason'])
                    && isset($context['path']);
            });

        Log::makePartial();

        $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload(now()->format('YmdHis')),
            ['REMOTE_ADDR' => $this->unknownIp]
        );
    }

    public function test_middleware_applies_to_all_mpesa_api_routes(): void
    {
        $apiRoutes = [
            '/api/webhooks/mpesa/stk-callback',
            '/api/webhooks/mpesa/c2b/validation',
            '/api/webhooks/mpesa/c2b/confirmation',
            '/api/webhooks/mpesa/till/validation',
            '/api/webhooks/mpesa/till/confirmation',
            '/api/webhooks/mpesa/b2c/result',
            '/api/webhooks/mpesa/b2c/timeout',
        ];

        foreach ($apiRoutes as $route) {
            $response = $this->postJson(
                $route,
                ['test' => true],
                ['REMOTE_ADDR' => $this->unknownIp]
            );

            $this->assertEquals(
                403,
                $response->status(),
                "Expected 403 for {$route} from unauthorized IP, got {$response->status()}"
            );
        }
    }

    public function test_middleware_applies_to_all_mpesa_web_routes(): void
    {
        $webRoutes = [
            '/webhooks/mpesa/stk-callback',
            '/webhooks/mpesa/c2b/validation',
            '/webhooks/mpesa/c2b/confirmation',
        ];

        foreach ($webRoutes as $route) {
            $response = $this->postJson(
                $route,
                ['test' => true],
                ['REMOTE_ADDR' => $this->unknownIp]
            );

            $this->assertEquals(
                403,
                $response->status(),
                "Expected 403 for {$route} from unauthorized IP, got {$response->status()}"
            );
        }
    }

    public function test_configurable_timestamp_tolerance(): void
    {
        config(['payments.webhook_security.mpesa.timestamp_tolerance_minutes' => 5]);

        $tenMinutesAgo = now()->subMinutes(10)->format('YmdHis');

        $response = $this->postJson(
            $this->stkCallbackApiRoute,
            $this->makeStkPayload($tenMinutesAgo),
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(403);
    }
}
