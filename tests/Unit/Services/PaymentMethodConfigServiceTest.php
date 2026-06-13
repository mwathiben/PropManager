<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\SecurityLogger;
use App\Services\Settings\PaymentMethodConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentMethodConfigServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private PaymentMethodConfigService $service;

    private User $landlord;

    private PaymentConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentMethodConfigService;

        $data = $this->createLandlordWithFullSetup();
        $this->landlord = $data['landlord'];

        $this->config = PaymentConfiguration::factory()->create([
            'landlord_id' => $this->landlord->id,
            'paystack_secret_key' => 'sk_test_existing1234',
            'paystack_public_key' => 'pk_test_existing',
            'mpesa_consumer_key' => 'mpesa_key_existing',
            'mpesa_consumer_secret' => 'mpesa_secret_existing',
            'mpesa_passkey' => 'passkey_existing',
            'mpesa_b2c_password' => 'b2c_pass_existing',
            'mpesa_b2c_security_credential' => 'b2c_cred_existing',
            'intasend_secret_key' => 'intasend_key_existing',
            'intasend_webhook_challenge' => 'webhook_chal_existing',
            'accepted_payment_methods' => ['cash'],
        ]);
    }

    public function test_apply_blank_preserves_existing_secret_when_submitted_empty(): void
    {
        $logger = $this->createMock(SecurityLogger::class);
        $logger->expects($this->once())->method('logPaymentConfigChange');

        $this->service->apply($this->landlord, [
            'accepted_payment_methods' => ['cash', 'paystack'],
            'paystack_public_key' => 'pk_test_new',
            'paystack_secret_key' => '',  // empty — must be preserved
        ], $logger);

        $this->config->refresh();

        $this->assertSame('sk_test_existing1234', $this->config->paystack_secret_key);
        $this->assertSame('pk_test_new', $this->config->paystack_public_key);
    }

    public function test_apply_overwrites_secret_when_new_value_is_given(): void
    {
        $logger = $this->createMock(SecurityLogger::class);
        $logger->expects($this->once())->method('logPaymentConfigChange');

        $this->service->apply($this->landlord, [
            'accepted_payment_methods' => ['cash', 'paystack'],
            'paystack_secret_key' => 'sk_test_brand_new_9999',
        ], $logger);

        $this->config->refresh();

        $this->assertSame('sk_test_brand_new_9999', $this->config->paystack_secret_key);
    }

    public function test_apply_preserves_all_secret_fields_when_blank(): void
    {
        $logger = $this->createMock(SecurityLogger::class);
        $logger->expects($this->once())->method('logPaymentConfigChange');

        $this->service->apply($this->landlord, [
            'accepted_payment_methods' => ['cash', 'mobile_money'],
            'paystack_secret_key' => '',
            'mpesa_passkey' => '',
            'mpesa_consumer_key' => '',
            'mpesa_consumer_secret' => '',
            'mpesa_b2c_password' => '',
            'mpesa_b2c_security_credential' => '',
            'intasend_secret_key' => '',
        ], $logger);

        $this->config->refresh();

        $this->assertSame('sk_test_existing1234', $this->config->paystack_secret_key);
        $this->assertSame('mpesa_key_existing', $this->config->mpesa_consumer_key);
        $this->assertSame('mpesa_secret_existing', $this->config->mpesa_consumer_secret);
        $this->assertSame('passkey_existing', $this->config->mpesa_passkey);
        $this->assertSame('b2c_pass_existing', $this->config->mpesa_b2c_password);
        $this->assertSame('b2c_cred_existing', $this->config->mpesa_b2c_security_credential);
        $this->assertSame('intasend_key_existing', $this->config->intasend_secret_key);
    }

    public function test_apply_does_not_audit_when_nothing_changed(): void
    {
        $logger = $this->createMock(SecurityLogger::class);
        $logger->expects($this->never())->method('logPaymentConfigChange');

        $this->service->apply($this->landlord, [
            'accepted_payment_methods' => ['cash'],  // same as existing
        ], $logger);
    }

    public function test_masked_config_returns_last4_for_paystack_secret(): void
    {
        $result = $this->service->maskedConfig($this->landlord);

        $this->assertArrayHasKey('paystack_secret_key_last4', $result);
        $this->assertSame('****1234', $result['paystack_secret_key_last4']);
    }

    public function test_masked_config_never_returns_raw_secrets(): void
    {
        $result = $this->service->maskedConfig($this->landlord);

        $this->assertArrayNotHasKey('paystack_secret_key', $result);
        $this->assertArrayNotHasKey('mpesa_consumer_key', $result);
        $this->assertArrayNotHasKey('mpesa_consumer_secret', $result);
        $this->assertArrayNotHasKey('mpesa_passkey', $result);
        $this->assertArrayNotHasKey('mpesa_b2c_password', $result);
        $this->assertArrayNotHasKey('mpesa_b2c_security_credential', $result);
        $this->assertArrayNotHasKey('intasend_secret_key', $result);
        $this->assertArrayNotHasKey('intasend_webhook_challenge', $result);
    }

    public function test_masked_config_returns_null_last4_when_no_secret_configured(): void
    {
        $this->config->update(['paystack_secret_key' => null]);

        $result = $this->service->maskedConfig($this->landlord);

        $this->assertNull($result['paystack_secret_key_last4']);
    }

    public function test_masked_config_returns_last4_for_all_secret_fields(): void
    {
        $result = $this->service->maskedConfig($this->landlord);

        $this->assertSame('****1234', $result['paystack_secret_key_last4']);
        $this->assertSame('****ting', $result['mpesa_consumer_key_last4']);
        $this->assertSame('****ting', $result['mpesa_consumer_secret_last4']);
        $this->assertSame('****ting', $result['mpesa_b2c_password_last4']);
        $this->assertSame('****ting', $result['mpesa_b2c_security_credential_last4']);
        $this->assertSame('****ting', $result['intasend_secret_key_last4']);
    }
}
