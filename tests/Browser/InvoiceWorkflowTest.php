<?php

namespace Tests\Browser;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InvoiceWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function createLeaseWithInvoice(array $setup): array
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $setup['landlord']->id,
        ]);

        $lease = Lease::create([
            'unit_id' => $setup['units']->first()->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $setup['landlord']->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
        ]);

        $setup['units']->first()->update(['status' => 'occupied']);

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'invoice_number' => 'INV-'.date('Ym').'-0001',
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
        ]);

        return compact('tenant', 'lease', 'invoice');
    }

    public function test_landlord_can_view_invoices(): void
    {
        $setup = $this->createLandlordWithProperty();
        $this->createLeaseWithInvoice($setup);

        $this->browse(function (Browser $browser) use ($setup) {
            $browser->loginAs($setup['landlord'])
                ->visit('/invoices')
                ->assertSee('INV-');
        });
    }

    public function test_landlord_can_view_single_invoice(): void
    {
        $setup = $this->createLandlordWithProperty();
        ['invoice' => $invoice] = $this->createLeaseWithInvoice($setup);

        $this->browse(function (Browser $browser) use ($setup, $invoice) {
            $browser->loginAs($setup['landlord'])
                ->visit("/invoices/{$invoice->id}")
                ->assertSee($invoice->invoice_number)
                ->assertSee('25,000');
        });
    }

    public function test_invoice_shows_payment_history(): void
    {
        $setup = $this->createLandlordWithProperty();
        ['invoice' => $invoice, 'lease' => $lease] = $this->createLeaseWithInvoice($setup);

        $invoice->payments()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $lease->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'CASH-001',
        ]);

        $this->browse(function (Browser $browser) use ($setup, $invoice) {
            $browser->loginAs($setup['landlord'])
                ->visit("/invoices/{$invoice->id}")
                ->assertSee('Payment')
                ->assertSee('CASH-001');
        });
    }
}
