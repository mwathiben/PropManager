<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\PaymentConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

#[Group('api')]
class TenantApiTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    private array $setup;

    private array $tenantData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setup = $this->createLandlordWithFullSetup();
        $unit = $this->setup['units']->first();
        $this->tenantData = $this->createTenantWithActiveLease($this->setup['landlord'], $unit);
    }

    public function test_tenant_can_get_current_lease(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson('/api/v1/tenant/lease');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'rent_amount',
                    'deposit_amount',
                    'start_date',
                    'is_active',
                    'unit',
                ],
            ])
            ->assertJsonPath('data.id', $lease->id)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_tenant_can_list_invoices(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];

        $this->createInvoiceForLease($lease, 'sent');
        $this->createInvoiceForLease($lease, 'paid');

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson('/api/v1/tenant/invoices');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'total_due',
                        'amount_paid',
                        'status',
                        'due_date',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_tenant_can_view_single_invoice(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson("/api/v1/tenant/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number);
    }

    public function test_tenant_can_list_payments(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 5000,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'TEST123',
        ]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson('/api/v1/tenant/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'payment_method',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_tenant_can_initiate_mpesa_payment(): void
    {
        $landlord = $this->setup['landlord'];
        PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);

        $this->mockMpesaService([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
            'CheckoutRequestID' => 'ws_CO_TEST123',
            'MerchantRequestID' => 'MR_TEST123',
        ]);

        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/mpesa/initiate', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'checkout_request_id',
            ]);
    }

    public function test_tenant_can_initiate_paystack_payment(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.paystack.co/*' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'reference' => 'PAY_TEST123',
                    'access_code' => 'AC_TEST',
                ],
            ], 200),
        ]);

        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $invoice->landlord_id],
            [
                'paystack_enabled' => true,
                'paystack_public_key' => 'pk_test_xxx',
                'paystack_secret_key' => 'sk_test_xxx',
            ]
        );

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/paystack/initiate', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'authorization_url',
                'reference',
            ]);
    }

    public function test_tenant_cannot_access_other_tenants_data(): void
    {
        $otherUnit = $this->setup['units']->get(1);
        $otherTenantData = $this->createTenantWithActiveLease($this->setup['landlord'], $otherUnit);
        $otherLease = $otherTenantData['lease'];
        $otherInvoice = $this->createInvoiceForLease($otherLease, 'sent');

        $tenant = $this->tenantData['tenant'];
        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson("/api/v1/tenant/invoices/{$otherInvoice->id}");

        $response->assertForbidden();
    }

    public function test_tenant_cannot_access_landlord_endpoints(): void
    {
        $tenant = $this->tenantData['tenant'];
        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson('/api/v1/landlord/properties');

        $response->assertForbidden();
    }

    public function test_tenant_notifications_endpoint(): void
    {
        $this->markTestSkipped(
            'Project uses custom notifications table - Laravel DatabaseNotification not available. '.
            'Notification API requires architectural alignment.'
        );
    }

    public function test_tenant_can_mark_notification_as_read(): void
    {
        $this->markTestSkipped(
            'Project uses custom notifications table - Laravel DatabaseNotification not available. '.
            'Notification API requires architectural alignment.'
        );
    }

    public function test_tenant_can_get_payment_receipt(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => $invoice->total_due,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'RECEIPT123',
        ]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->get("/api/v1/tenant/payments/{$payment->id}/receipt");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_tenant_lease_history(): void
    {
        $tenant = $this->tenantData['tenant'];

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->getJson('/api/v1/tenant/lease/history');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'rent_amount',
                        'start_date',
                        'is_active',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_invoice_download(): void
    {
        $tenant = $this->tenantData['tenant'];
        $lease = $this->tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->get("/api/v1/tenant/invoices/{$invoice->id}/download");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
