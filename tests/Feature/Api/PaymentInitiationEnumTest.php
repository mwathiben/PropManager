<?php

namespace Tests\Feature\Api;

use App\Models\PaymentConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentInitiationEnumTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_paystack_rejects_paid_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        PaymentConfiguration::factory()->withPaystack()->create(['landlord_id' => $landlord->id]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/paystack/initiate', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
        ]);

        $response->assertJsonValidationErrors('invoice_id');
    }

    public function test_mpesa_rejects_paid_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        PaymentConfiguration::factory()->withMpesa()->create(['landlord_id' => $landlord->id]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/mpesa/initiate', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response->assertJsonValidationErrors('invoice_id');
    }

    public function test_intasend_rejects_paid_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord->id,
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_1234',
            'intasend_secret_key' => 'ISSecretKey_test_1234',
        ]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->postJson('/api/v1/tenant/payments/intasend/initiate', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'phone' => '0712345678',
        ]);

        $response->assertJsonValidationErrors('invoice_id');
    }
}
