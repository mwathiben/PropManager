<?php

namespace Tests\Feature\Controllers;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class MpesaControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected User $tenant;

    protected Lease $lease;

    protected Invoice $invoice;

    protected PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];

        $tenantSetup = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first()
        );
        $this->tenant = $tenantSetup['tenant'];
        $this->lease = $tenantSetup['lease'];

        $this->invoice = $this->createInvoiceForLease($this->lease);

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['mobile_money'],
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
            'mpesa_environment' => 'sandbox',
        ]);
    }

    public function test_initiate_stk_push_uses_landlord_payment_config(): void
    {
        $mockService = Mockery::mock(MpesaService::class);

        $mockService->shouldReceive('initiateSTKPush')
            ->once()
            ->withArgs(function ($data, $config) {
                return isset($data['phone'])
                    && isset($data['amount'])
                    && $data['amount'] === 1000
                    && $config instanceof PaymentConfiguration
                    && $config->id === $this->paymentConfig->id;
            })
            ->andReturn([
                'ResponseCode' => '0',
                'CheckoutRequestID' => 'ws_CO_123',
                'MerchantRequestID' => 'mr_123',
            ]);

        $this->app->instance(MpesaService::class, $mockService);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/stk-push', [
                'invoice_id' => $this->invoice->id,
                'phone' => '0712345678',
                'amount' => 1000,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'checkout_request_id' => 'ws_CO_123',
            ]);
    }

    public function test_initiate_stk_push_returns_503_when_mpesa_not_configured(): void
    {
        $this->paymentConfig->update([
            'mpesa_consumer_key' => null,
            'mpesa_consumer_secret' => null,
            'mpesa_shortcode' => null,
            'mpesa_passkey' => null,
        ]);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/stk-push', [
                'invoice_id' => $this->invoice->id,
                'phone' => '0712345678',
                'amount' => 1000,
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ]);
    }

    public function test_check_status_uses_landlord_payment_config(): void
    {
        $mockService = Mockery::mock(MpesaService::class);

        $mockService->shouldReceive('querySTKStatus')
            ->once()
            ->withArgs(function ($checkoutRequestId, $config) {
                return $checkoutRequestId === 'ws_CO_123'
                    && $config instanceof PaymentConfiguration
                    && $config->id === $this->paymentConfig->id;
            })
            ->andReturn([
                'ResultCode' => '0',
                'ResultDesc' => 'Success',
            ]);

        $this->app->instance(MpesaService::class, $mockService);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/status', [
                'checkout_request_id' => 'ws_CO_123',
                'invoice_id' => $this->invoice->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'result_code' => '0',
            ]);
    }

    public function test_check_status_returns_503_when_mpesa_not_configured(): void
    {
        $this->paymentConfig->update([
            'mpesa_consumer_key' => null,
            'mpesa_consumer_secret' => null,
            'mpesa_shortcode' => null,
            'mpesa_passkey' => null,
        ]);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/status', [
                'checkout_request_id' => 'ws_CO_123',
                'invoice_id' => $this->invoice->id,
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ]);
    }

    public function test_initiate_stk_push_requires_invoice_id(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/stk-push', [
                'phone' => '0712345678',
                'amount' => 1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_id']);
    }

    public function test_check_status_requires_invoice_id_to_load_config(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/mpesa/status', [
                'checkout_request_id' => 'ws_CO_123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
