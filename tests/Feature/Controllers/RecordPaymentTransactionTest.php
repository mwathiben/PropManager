<?php

namespace Tests\Feature\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class RecordPaymentTransactionTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_record_payment_creates_all_records_atomically(): void
    {
        Mail::fake();
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => 5000,
                'payment_method' => 'cash',
                'reference' => 'TEST-REF-001',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
        ]);
        $invoice->refresh();
        $this->assertEquals(5000, (float) $invoice->amount_paid);

        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment->id,
        ]);
    }

    public function test_record_payment_rolls_back_on_receipt_failure(): void
    {
        Mail::fake();
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $mock = $this->mock(ReceiptService::class);
        $mock->shouldReceive('createReceipt')
            ->once()
            ->andThrow(new \RuntimeException('Receipt generation failed'));

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => 5000,
                'payment_method' => 'cash',
            ]
        );

        $response->assertStatus(500);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(0, (float) $invoice->amount_paid);
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
    }

    public function test_record_payment_full_payment_marks_invoice_paid(): void
    {
        Mail::fake();
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => $invoice->total_due,
                'payment_method' => 'bank_transfer',
            ]
        );

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_reissue_creates_invoice_and_items_atomically(): void
    {
        Mail::fake();

        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'voided');

        $invoice->items()->create([
            'item_type' => 'rent',
            'description' => 'Rent',
            'quantity' => 1,
            'unit_price' => 25000,
            'total' => 25000,
            'sort_order' => 1,
        ]);

        $originalCount = Invoice::count();

        $response = $this->actingAs($landlord)->post(route('invoices.reissue', $invoice));

        $response->assertRedirect();
        $this->assertEquals($originalCount + 1, Invoice::count());

        $newInvoice = Invoice::where('id', '!=', $invoice->id)
            ->where('landlord_id', $landlord->id)
            ->latest('id')
            ->first();

        $this->assertEquals(InvoiceStatus::Draft, $newInvoice->status);
        $this->assertEquals(1, $newInvoice->items()->count());
    }

    public function test_record_payment_validation_rejects_invalid_amount(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => 0,
                'payment_method' => 'cash',
            ]
        );

        $response->assertSessionHasErrors('amount');
    }

    public function test_record_payment_validation_rejects_invalid_method(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => 5000,
                'payment_method' => 'bitcoin',
            ]
        );

        $response->assertSessionHasErrors('payment_method');
    }
}
