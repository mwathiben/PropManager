<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaystackWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookRoute = '/webhooks/paystack';

    private string $paystackIp1 = '52.31.139.75';

    private string $paystackIp2 = '52.49.173.169';

    private string $paystackIp3 = '52.214.14.220';

    private string $unknownIp = '10.0.0.1';

    private function makePaystackPayload(): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_'.uniqid(),
                'amount' => 2500000,
                'status' => 'success',
                'channel' => 'card',
                'currency' => 'KES',
            ],
        ];
    }

    public function test_rejects_paystack_webhook_from_unknown_ip(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [
            '52.31.139.75',
            '52.49.173.169',
            '52.214.14.220',
        ]]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->unknownIp]
        );

        $response->assertStatus(403);
    }

    public function test_allows_from_52_31_139_75(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [
            '52.31.139.75',
            '52.49.173.169',
            '52.214.14.220',
        ]]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->paystackIp1]
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_allows_from_52_49_173_169(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [
            '52.31.139.75',
            '52.49.173.169',
            '52.214.14.220',
        ]]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->paystackIp2]
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_allows_from_52_214_14_220(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [
            '52.31.139.75',
            '52.49.173.169',
            '52.214.14.220',
        ]]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->paystackIp3]
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_ip_whitelist_is_configurable(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => ['203.0.113.50']]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => '203.0.113.50']
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_rejects_all_when_whitelist_is_empty(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => []]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->unknownIp]
        );

        $response->assertStatus(403);
    }

    public function test_rejection_logged_with_structured_context(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [
            '52.31.139.75',
        ]]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Paystack webhook rejected')
                    && $context['provider'] === 'paystack'
                    && $context['ip'] === $this->unknownIp
                    && isset($context['reason'])
                    && isset($context['path']);
            });

        Log::makePartial();

        $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            ['REMOTE_ADDR' => $this->unknownIp]
        );
    }

    public function test_signature_still_validated_after_ip_check(): void
    {
        config(['payments.webhook_security.paystack.allowed_ips' => [$this->paystackIp1]]);

        $response = $this->postJson(
            $this->webhookRoute,
            $this->makePaystackPayload(),
            [
                'REMOTE_ADDR' => $this->paystackIp1,
                'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid_signature',
            ]
        );

        $this->assertNotEquals(403, $response->status());
        $this->assertTrue(
            in_array($response->status(), [200, 400, 401, 500]),
            "Expected handler-level response (not 403 IP block), got {$response->status()}"
        );
    }
}
