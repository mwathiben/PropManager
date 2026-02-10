<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class PaymentGatewayRetryConfigTest extends TestCase
{
    public function test_paystack_gateway_config_has_required_keys(): void
    {
        $config = config('payments.gateways.paystack');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('timeout_seconds', $config);
        $this->assertArrayHasKey('retry_attempts', $config);
        $this->assertArrayHasKey('retry_delay_ms', $config);
        $this->assertArrayHasKey('retry_backoff_base', $config);
    }

    public function test_mpesa_gateway_config_has_required_keys(): void
    {
        $config = config('payments.gateways.mpesa');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('timeout_seconds', $config);
        $this->assertArrayHasKey('retry_attempts', $config);
        $this->assertArrayHasKey('retry_delay_ms', $config);
        $this->assertArrayHasKey('retry_backoff_base', $config);
    }

    public function test_intasend_gateway_config_has_required_keys(): void
    {
        $config = config('payments.gateways.intasend');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('timeout_seconds', $config);
        $this->assertArrayHasKey('retry_attempts', $config);
        $this->assertArrayHasKey('retry_delay_ms', $config);
        $this->assertArrayHasKey('retry_backoff_base', $config);
    }

    public function test_mpesa_retry_attempts_is_five(): void
    {
        $this->assertEquals(5, config('payments.gateways.mpesa.retry_attempts'));
    }

    public function test_old_intasend_retry_config_removed(): void
    {
        $this->assertNull(config('intasend.timeout'));
        $this->assertNull(config('intasend.retry_times'));
        $this->assertNull(config('intasend.retry_sleep'));
    }

    public function test_all_gateways_have_exponential_backoff_base(): void
    {
        foreach (['paystack', 'mpesa', 'intasend'] as $gateway) {
            $base = config("payments.gateways.{$gateway}.retry_backoff_base");
            $this->assertNotNull($base, "Gateway '{$gateway}' missing retry_backoff_base");
            $this->assertGreaterThanOrEqual(2, $base, "Gateway '{$gateway}' backoff base should be >= 2");
        }
    }
}
