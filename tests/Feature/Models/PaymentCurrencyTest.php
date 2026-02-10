<?php

namespace Tests\Feature\Models;

use App\Enums\Currency;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentCurrencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    #[Test]
    public function payment_created_with_explicit_currency(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 5000,
            'currency' => 'USD',
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'TEST-001',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'currency' => 'USD',
        ]);
    }

    #[Test]
    public function payment_defaults_to_kes_when_currency_omitted(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'TEST-002',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'currency' => 'KES',
        ]);
    }

    #[Test]
    public function payment_currency_is_cast_to_enum(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 5000,
            'currency' => 'EUR',
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'TEST-003',
        ]);

        $payment->refresh();

        $this->assertInstanceOf(Currency::class, $payment->currency);
        $this->assertSame(Currency::EUR, $payment->currency);
    }

    #[Test]
    public function payment_currency_appears_in_api_resource(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 5000,
            'currency' => 'GBP',
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'TEST-004',
        ]);

        $resource = new \App\Http\Resources\PaymentResource($payment);
        $array = $resource->toArray(request());

        $this->assertSame('GBP', $array['currency']);
    }

    #[Test]
    public function all_supported_currencies_can_be_stored(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease);

        foreach (Currency::cases() as $currency) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'lease_id' => $lease->id,
                'landlord_id' => $landlord->id,
                'amount' => 1000,
                'currency' => $currency->value,
                'payment_method' => 'cash',
                'payment_date' => now(),
                'reference' => 'TEST-'.$currency->value,
            ]);

            $payment->refresh();
            $this->assertSame($currency, $payment->currency);
        }
    }
}
