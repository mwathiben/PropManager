<?php

namespace Tests\Feature;

use App\Models\PaymentConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class PaymentRateLimitingTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    private array $setup;

    private array $tenantData;

    private $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setup = $this->createLandlordWithFullSetup();
        $unit = $this->setup['units']->first();
        $this->tenantData = $this->createTenantWithActiveLease($this->setup['landlord'], $unit);
        $this->invoice = $this->createInvoiceForLease($this->tenantData['lease'], 'sent');

        PaymentConfiguration::create([
            'landlord_id' => $this->setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_xxxxx',
            'paystack_secret_key' => 'sk_test_xxxxx',
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_123',
            'intasend_secret_key' => 'ISSecretKey_test_456',
            'intasend_environment' => 'sandbox',
            'intasend_webhook_challenge' => 'test_challenge',
        ]);

        $this->mockPaystackService([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/test',
                'reference' => 'PAY-TEST-'.uniqid(),
                'access_code' => 'test_access',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_first_payment_request_per_invoice_succeeds(): void
    {
        $response = $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $this->assertNotEquals(429, $response->status());
    }

    public function test_second_payment_request_for_same_invoice_returns_429(): void
    {
        $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response = $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response->assertStatus(429);
        $response->assertJsonPath('message', 'A payment for this invoice is already being processed. Please wait before trying again.');
    }

    public function test_different_invoices_are_independently_rate_limited(): void
    {
        $secondUnit = $this->setup['units']->skip(1)->first();
        $secondData = $this->createTenantWithActiveLease($this->setup['landlord'], $secondUnit);
        $secondInvoice = $this->createInvoiceForLease($secondData['lease'], 'sent');

        $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response = $this->actingAs($secondData['tenant'])
            ->postJson(route('payments.paystack.initialize', $secondInvoice), [
                'amount' => 10000,
            ]);

        $this->assertNotEquals(429, $response->status());
    }

    public function test_per_invoice_limit_applies_across_payment_providers(): void
    {
        Sanctum::actingAs($this->tenantData['tenant'], ['tenant:read']);

        $this->postJson('/api/v1/tenant/payments/intasend/initiate', [
            'invoice_id' => $this->invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response = $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 5000,
            ]);

        $response->assertStatus(429);
    }

    public function test_429_response_includes_retry_after_header(): void
    {
        $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response = $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_429_response_includes_retry_after_in_body(): void
    {
        $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response = $this->actingAs($this->tenantData['tenant'])
            ->postJson(route('payments.paystack.initialize', $this->invoice), [
                'amount' => 10000,
            ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_per_user_limit_still_enforced_alongside_per_invoice(): void
    {
        $this->assertGreaterThanOrEqual(6, $this->setup['units']->count());
        $units = $this->setup['units']->take(6);
        $invoices = [];

        foreach ($units as $unit) {
            if ($unit->id === $this->setup['units']->first()->id) {
                $invoices[] = $this->invoice;

                continue;
            }
            $data = $this->createTenantWithActiveLease($this->setup['landlord'], $unit);
            $invoices[] = $this->createInvoiceForLease($data['lease'], 'sent');
        }

        $lastResponse = null;
        foreach ($invoices as $invoice) {
            $lastResponse = $this->actingAs($this->tenantData['tenant'])
                ->postJson(route('payments.paystack.initialize', $invoice), [
                    'amount' => 1000,
                ]);
        }

        $this->assertEquals(429, $lastResponse->status());
    }

    public function test_api_intasend_route_enforces_per_invoice_limit(): void
    {
        Sanctum::actingAs($this->tenantData['tenant'], ['tenant:read']);

        $this->postJson('/api/v1/tenant/payments/intasend/initiate', [
            'invoice_id' => $this->invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response = $this->postJson('/api/v1/tenant/payments/intasend/initiate', [
            'invoice_id' => $this->invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response->assertStatus(429);
    }
}
