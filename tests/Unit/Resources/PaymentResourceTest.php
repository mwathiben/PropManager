<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentResourceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    #[Test]
    public function transforms_payment_to_correct_structure(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-001',
            'notes' => 'Test note',
        ]);

        $result = (new PaymentResource($payment))->resolve();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('payment_method', $result);
        $this->assertArrayHasKey('payment_date', $result);
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('notes', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertSame($payment->id, $result['id']);
        $this->assertSame('cash', $result['payment_method']);
        $this->assertSame('RES-TEST-001', $result['reference']);
        $this->assertSame('Test note', $result['notes']);
    }

    #[Test]
    public function amount_cast_to_float_from_decimal(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => '5000.50',
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-002',
        ]);

        $result = (new PaymentResource($payment))->resolve();

        $this->assertIsFloat($result['amount']);
        $this->assertSame(5000.50, $result['amount']);
    }

    #[Test]
    public function dates_formatted_as_iso8601_strings(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => '2026-02-09',
            'reference' => 'RES-TEST-003',
        ]);

        $result = (new PaymentResource($payment))->resolve();

        $this->assertNotNull($result['payment_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $result['payment_date']);
        $this->assertNotNull($result['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $result['created_at']);
    }

    #[Test]
    public function null_payment_date_returns_null(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-004',
        ]);

        // Simulate null date on already-loaded model (DB requires non-null)
        $payment->payment_date = null;

        $result = (new PaymentResource($payment))->resolve();

        $this->assertNull($result['payment_date']);
    }

    #[Test]
    public function invoice_included_when_relationship_loaded(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'RES-TEST-005',
        ]);

        $payment->load('invoice');

        $result = (new PaymentResource($payment))->resolve();

        $this->assertIsArray($result['invoice']);
        $this->assertArrayHasKey('id', $result['invoice']);
        $this->assertArrayHasKey('invoice_number', $result['invoice']);
        $this->assertArrayHasKey('total_due', $result['invoice']);
        $this->assertSame($invoice->id, $result['invoice']['id']);
        $this->assertIsFloat($result['invoice']['total_due']);
    }

    #[Test]
    public function invoice_excluded_when_not_loaded(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-006',
        ]);

        $payment->unsetRelation('invoice');

        $result = (new PaymentResource($payment))->resolve();

        // whenLoaded returns MissingValue which is stripped by resolve()
        $this->assertArrayNotHasKey('invoice', $result);
    }

    #[Test]
    public function unit_included_when_lease_loaded_with_unit_building(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'RES-TEST-007',
        ]);

        $payment->load('lease.unit.building');

        $result = (new PaymentResource($payment))->resolve();

        $this->assertIsArray($result['unit']);
        $this->assertArrayHasKey('id', $result['unit']);
        $this->assertArrayHasKey('unit_number', $result['unit']);
        $this->assertArrayHasKey('building', $result['unit']);
        $this->assertSame($unit->id, $result['unit']['id']);
        $this->assertSame('Block A', $result['unit']['building']);
    }

    #[Test]
    public function unit_excluded_when_lease_not_loaded(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-008',
        ]);

        $payment->unsetRelation('lease');

        $result = (new PaymentResource($payment))->resolve();

        // whenLoaded returns MissingValue which is stripped by resolve()
        $this->assertArrayNotHasKey('unit', $result);
    }

    #[Test]
    public function building_name_null_safe_when_no_building(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RES-TEST-009',
        ]);

        // Load lease with unit, but set building to null
        $payment->load('lease.unit');
        $payment->lease->unit->setRelation('building', null);

        $result = (new PaymentResource($payment))->resolve();

        $this->assertIsArray($result['unit']);
        $this->assertNull($result['unit']['building']);
    }
}
