<?php

namespace Tests\Feature\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_landlord_can_view_payments_hub(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'CASH-001',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.overview'));

        $response->assertOk();
    }

    public function test_paystack_callback_processes_correctly(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'paystack_reference' => 'PAY-123456',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments.callback', ['reference' => 'PAY-123456']));

        $response->assertRedirect();
    }

    public function test_payment_receipt_download(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'CASH-RECEIPT',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments.downloadReceipt', $payment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_get_paystack_public_key(): void
    {
        config(['services.paystack.public_key' => 'pk_test_xxxxx']);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments.publicKey'));

        $response->assertOk();
        $response->assertJson(['public_key' => 'pk_test_xxxxx']);
    }

    public function test_payment_transactions_page(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'CASH-FILTER',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.transactions'));

        $response->assertOk();
    }
}
