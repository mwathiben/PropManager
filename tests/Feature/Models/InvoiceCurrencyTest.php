<?php

namespace Tests\Feature\Models;

use App\Enums\Currency;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InvoiceCurrencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    #[Test]
    public function invoice_created_with_explicit_currency(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-001',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 30000,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'currency' => 'USD',
        ]);
    }

    #[Test]
    public function invoice_defaults_to_kes_when_currency_omitted(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-002',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 30000,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'currency' => 'KES',
        ]);
    }

    #[Test]
    public function invoice_currency_is_cast_to_enum(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-003',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 30000,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'draft',
        ]);

        $invoice->refresh();

        $this->assertInstanceOf(Currency::class, $invoice->currency);
        $this->assertSame(Currency::GBP, $invoice->currency);
    }

    #[Test]
    public function invoice_currency_appears_in_api_resource(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-004',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 30000,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'currency' => 'EUR',
            'status' => 'draft',
        ]);

        $resource = new \App\Http\Resources\InvoiceResource($invoice);
        $array = $resource->toArray(request());

        $this->assertSame('EUR', $array['currency']);
    }

    #[Test]
    public function all_supported_currencies_can_be_stored(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        foreach (Currency::cases() as $i => $currency) {
            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'landlord_id' => $landlord->id,
                'invoice_number' => "INV-CURR-{$currency->value}",
                'due_date' => now()->addDays(7),
                'billing_period_start' => now()->startOfMonth(),
                'rent_due' => 30000,
                'water_due' => 0,
                'arrears' => 0,
                'total_due' => 30000,
                'amount_paid' => 0,
                'currency' => $currency->value,
                'status' => 'draft',
            ]);

            $invoice->refresh();
            $this->assertSame($currency, $invoice->currency);
        }
    }
}
