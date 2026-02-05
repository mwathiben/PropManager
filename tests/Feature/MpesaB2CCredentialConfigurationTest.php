<?php

namespace Tests\Feature;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PAY-V2-006: M-Pesa B2C Credential Configuration Tests
 *
 * Tests for:
 * - B2C credential encryption at rest
 * - Per-landlord B2C config detection
 * - Last 4 chars display (no full secret to frontend)
 * - Blank-preserves-existing / overwrite-when-provided patterns
 * - RefundService loads config before B2C call
 */
class MpesaB2CCredentialConfigurationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private PaymentConfiguration $paymentConfig;

    private array $setupData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $this->paymentConfig = PaymentConfiguration::factory()->create([
            'landlord_id' => $this->landlord->id,
            'mpesa_b2c_shortcode' => '600123',
            'mpesa_b2c_initiator' => 'testInitiator',
            'mpesa_b2c_password' => 'test_b2c_password_abcd',
            'mpesa_b2c_security_credential' => 'test_b2c_cred_xyz9876',
        ]);
    }

    public function test_b2c_password_is_encrypted_in_database(): void
    {
        $raw = DB::table('payment_configurations')
            ->where('id', $this->paymentConfig->id)
            ->value('mpesa_b2c_password');

        $this->assertNotEquals('test_b2c_password_abcd', $raw);

        $this->assertEquals(
            'test_b2c_password_abcd',
            $this->paymentConfig->fresh()->mpesa_b2c_password
        );
    }

    public function test_b2c_security_credential_is_encrypted_in_database(): void
    {
        $raw = DB::table('payment_configurations')
            ->where('id', $this->paymentConfig->id)
            ->value('mpesa_b2c_security_credential');

        $this->assertNotEquals('test_b2c_cred_xyz9876', $raw);

        $this->assertEquals(
            'test_b2c_cred_xyz9876',
            $this->paymentConfig->fresh()->mpesa_b2c_security_credential
        );
    }

    public function test_has_mpesa_b2c_config_returns_correct_values(): void
    {
        $this->assertTrue($this->paymentConfig->hasMpesaB2CConfig());

        $this->paymentConfig->update(['mpesa_b2c_shortcode' => null]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaB2CConfig());

        $this->paymentConfig->update([
            'mpesa_b2c_shortcode' => '600123',
            'mpesa_b2c_initiator' => null,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaB2CConfig());

        $this->paymentConfig->update([
            'mpesa_b2c_initiator' => 'testInitiator',
            'mpesa_b2c_password' => null,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaB2CConfig());

        $this->paymentConfig->update([
            'mpesa_b2c_password' => 'restored',
            'mpesa_b2c_security_credential' => null,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaB2CConfig());

        $this->paymentConfig->update([
            'mpesa_b2c_security_credential' => 'restored',
        ]);
        $this->assertTrue($this->paymentConfig->fresh()->hasMpesaB2CConfig());
    }

    public function test_settings_controller_returns_last4_for_b2c_password(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.mpesa_b2c_password_last4')
            ->where('paymentConfig.mpesa_b2c_password_last4', '****abcd')
        );
    }

    public function test_settings_controller_returns_last4_for_b2c_security_credential(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.mpesa_b2c_security_credential_last4')
            ->where('paymentConfig.mpesa_b2c_security_credential_last4', '****9876')
        );
    }

    public function test_b2c_secrets_never_exposed_to_frontend(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->missing('paymentConfig.mpesa_b2c_password')
            ->missing('paymentConfig.mpesa_b2c_security_credential')
        );
    }

    public function test_update_preserves_b2c_secrets_when_blank(): void
    {
        $originalPassword = $this->paymentConfig->mpesa_b2c_password;
        $originalCredential = $this->paymentConfig->mpesa_b2c_security_credential;

        $response = $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'mobile_money'],
                'mpesa_b2c_shortcode' => '999999',
                'mpesa_b2c_password' => '',
                'mpesa_b2c_security_credential' => '',
            ]);

        $response->assertRedirect();

        $this->paymentConfig->refresh();

        $this->assertEquals($originalPassword, $this->paymentConfig->mpesa_b2c_password);
        $this->assertEquals($originalCredential, $this->paymentConfig->mpesa_b2c_security_credential);
        $this->assertEquals('999999', $this->paymentConfig->mpesa_b2c_shortcode);
    }

    public function test_update_overwrites_b2c_secrets_when_provided(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'mobile_money'],
                'mpesa_b2c_password' => 'new_b2c_password_xxxx',
                'mpesa_b2c_security_credential' => 'new_b2c_cred_yyyy',
            ]);

        $response->assertRedirect();

        $this->paymentConfig->refresh();

        $this->assertEquals('new_b2c_password_xxxx', $this->paymentConfig->mpesa_b2c_password);
        $this->assertEquals('new_b2c_cred_yyyy', $this->paymentConfig->mpesa_b2c_security_credential);
    }

    public function test_refund_service_loads_config_before_b2c(): void
    {
        $mpesaService = $this->createMock(MpesaService::class);

        $mpesaService->expects($this->once())
            ->method('withConfig')
            ->with($this->callback(function (PaymentConfiguration $config) {
                return $config->landlord_id === $this->landlord->id;
            }))
            ->willReturnSelf();

        $mpesaService->expects($this->once())
            ->method('initiateB2C')
            ->willReturn(['conversation_id' => 'test_conv_id']);

        $refundService = new RefundService(
            $this->createMock(\App\Services\PaystackService::class),
            $mpesaService,
            $this->createMock(\App\Services\BillingModelService::class),
        );

        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $tenant->update(['mobile_number' => '0712345678']);

        $invoice = $this->createInvoiceForLease($lease);

        $payment = \App\Models\Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'TEST-PAY-'.uniqid(),
        ]);

        $refund = \App\Models\Refund::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 1000,
            'status' => 'processing',
            'reason' => 'Test refund',
            'payment_method' => 'mobile_money',
            'initiated_by' => $this->landlord->id,
        ]);

        $reflection = new \ReflectionMethod($refundService, 'processMpesaRefund');
        $result = $reflection->invoke($refundService, $refund);

        $this->assertTrue($result);
    }
}
