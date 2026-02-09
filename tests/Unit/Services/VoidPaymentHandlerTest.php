<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use App\Services\Payment\VoidPaymentHandler;
use App\Services\Payment\VoidPaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class VoidPaymentHandlerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected VoidPaymentHandler $handler;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(VoidPaymentHandler::class);
        $this->setupData = $this->createLandlordWithFullSetup();
    }

    public function test_void_marks_payment_as_voided(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-1',
        ]);

        $result = $this->handler->void($payment, 'Test void reason');

        $this->assertInstanceOf(VoidPaymentResult::class, $result);
        $payment->refresh();
        $this->assertTrue($payment->is_voided);
        $this->assertNotNull($payment->voided_at);
        $this->assertEquals('Test void reason', $payment->void_reason);
    }

    public function test_void_recalculates_invoice_amount_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'partial');
        $invoice->update(['amount_paid' => 15000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-2',
        ]);

        $result = $this->handler->void($payment, 'Duplicate payment');

        $this->assertTrue($result->invoiceWasRecalculated());
        $invoice->refresh();
        $this->assertEquals(0, $invoice->amount_paid);
    }

    public function test_void_changes_invoice_status_to_sent_when_fully_reversed(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'partial');
        $invoice->update(['amount_paid' => 10000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-3',
        ]);

        $result = $this->handler->void($payment, 'Full reversal');

        $this->assertEquals(InvoiceStatus::Sent, $result->newInvoiceStatus);
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertEquals(0, $invoice->amount_paid);
    }

    public function test_void_changes_invoice_status_to_partial_when_partially_reversed(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'partial');
        $invoice->update(['amount_paid' => 20000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-4',
        ]);

        $result = $this->handler->void($payment, 'Partial reversal');

        $this->assertEquals(InvoiceStatus::Partial, $result->newInvoiceStatus);
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Partial, $invoice->status);
        $this->assertEquals(10000, $invoice->amount_paid);
    }

    public function test_void_preserves_voided_invoice_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $invoice->update(['status' => InvoiceStatus::Voided, 'amount_paid' => 5000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-5',
        ]);

        $result = $this->handler->void($payment, 'Invoice already voided');

        $this->assertEquals(InvoiceStatus::Voided, $result->newInvoiceStatus);
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Voided, $invoice->status);
    }

    public function test_void_rejects_already_voided_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $payment = Payment::create([
            'invoice_id' => null,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-6',
            'is_voided' => true,
            'voided_at' => now(),
            'void_reason' => 'Previously voided',
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment is already voided.');

        $this->handler->void($payment, 'Try to void again');
    }

    public function test_void_handles_payment_without_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $payment = Payment::create([
            'invoice_id' => null,
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'VOID-UNIT-TEST-7',
        ]);

        $result = $this->handler->void($payment, 'No invoice attached');

        $this->assertFalse($result->invoiceWasRecalculated());
        $this->assertNull($result->invoice);
        $payment->refresh();
        $this->assertTrue($payment->is_voided);
    }
}
