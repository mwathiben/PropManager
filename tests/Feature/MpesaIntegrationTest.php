<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class MpesaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Invoice $invoice;

    private Lease $lease;

    private PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mpesa.allowed_ips' => []]);

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $this->paymentConfig = PaymentConfiguration::factory()->forLandlord($this->landlord)->create([
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'mobile_number' => '254712345678',
        ]);

        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'invoice_number' => 'INV-202601-0001',
            'rent_due' => 25000,
            'water_due' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_stk_push_initiation_succeeds(): void
    {
        $checkoutRequestId = 'ws_CO_'.date('dmYHis').rand(100000, 999999);

        $mock = Mockery::mock(MpesaService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('initiateSTKPush')->andReturn([
            'MerchantRequestID' => 'MR_'.uniqid(),
            'CheckoutRequestID' => $checkoutRequestId,
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]);
        $this->app->instance(MpesaService::class, $mock);

        Sanctum::actingAs($this->tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/mpesa/initiate', [
            'invoice_id' => $this->invoice->id,
            'phone' => '254712345678',
            'amount' => 25000,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['checkout_request_id', 'message']);
    }

    public function test_stk_push_with_invalid_phone_fails(): void
    {
        $mock = Mockery::mock(MpesaService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(MpesaService::class, $mock);

        Sanctum::actingAs($this->tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/mpesa/initiate', [
            'invoice_id' => $this->invoice->id,
            'amount' => 25000,
        ]);

        $response->assertStatus(422);
    }

    public function test_stk_callback_from_non_safaricom_ip_returns_403(): void
    {
        config(['mpesa.allowed_ips' => ['196.201.214.200']]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => 'ws_CO_'.uniqid(),
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 25000],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QKL'.rand(100000000, 999999999)],
                            ['Name' => 'PhoneNumber', 'Value' => '254712345678'],
                            ['Name' => 'TransactionDate', 'Value' => now()->format('YmdHis')],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/mpesa/stk-callback', $payload, [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $response->assertStatus(403);
    }

    public function test_stk_callback_handles_failed_transaction(): void
    {
        $checkoutRequestId = 'ws_CO_'.uniqid();

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_'.uniqid(),
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/mpesa/stk-callback', $payload);

        $response->assertStatus(200);
        $response->assertJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        $this->assertDatabaseMissing('payments', [
            'mpesa_checkout_request_id' => $checkoutRequestId,
        ]);
    }

    public function test_c2b_validation_accepts_valid_payment(): void
    {
        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'QKL'.rand(100000000, 999999999),
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => 25000,
            'BusinessShortCode' => config('mpesa.c2b.shortcode'),
            'BillRefNumber' => $this->invoice->invoice_number,
            'MSISDN' => '254712345678',
        ];

        $response = $this->postJson('/webhooks/mpesa/c2b/validation', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    public function test_c2b_validation_rejects_invalid_invoice(): void
    {
        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'QKL'.rand(100000000, 999999999),
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => 25000,
            'BusinessShortCode' => config('mpesa.c2b.shortcode'),
            'BillRefNumber' => 'INVALID-INV-0000',
            'MSISDN' => '254712345678',
        ];

        $response = $this->postJson('/webhooks/mpesa/c2b/validation', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 'C2B00011',
            'ResultDesc' => 'Invalid Account Number',
        ]);
    }

    public function test_c2b_confirmation_creates_payment(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => 25000,
            'BusinessShortCode' => config('mpesa.c2b.shortcode'),
            'BillRefNumber' => $this->invoice->invoice_number,
            'MSISDN' => '254712345678',
        ];

        $response = $this->postJson('/webhooks/mpesa/c2b/confirmation', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ]);

        $this->assertDatabaseHas('payments', [
            'mpesa_transaction_id' => $transactionId,
            'invoice_id' => $this->invoice->id,
            'amount' => 25000,
            'payment_method' => 'mobile_money',
        ]);
    }

    public function test_duplicate_c2b_confirmation_is_idempotent(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => 25000,
            'BusinessShortCode' => config('mpesa.c2b.shortcode'),
            'BillRefNumber' => $this->invoice->invoice_number,
            'MSISDN' => '254712345678',
        ];

        $response1 = $this->postJson('/webhooks/mpesa/c2b/confirmation', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/webhooks/mpesa/c2b/confirmation', $payload);
        $response2->assertStatus(200);

        $this->assertEquals(1, Payment::where('mpesa_transaction_id', $transactionId)->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
