<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InvoiceWorkflowIntegrationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_complete_invoice_workflow(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease, 'tenant' => $tenant] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $invoiceService = app(InvoiceService::class);
        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $invoiceService->generateInvoiceForLease($lease, $billingPeriod);

        $this->assertNotNull($invoice);
        $this->assertEquals(InvoiceStatus::Draft, $invoice->status);
        $this->assertGreaterThan(0, $invoice->total_due);

        $this->actingAs($this->landlord)
            ->put(route('invoices.updateStatus', $invoice), ['status' => 'sent']);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);

        $partialAmount = $invoice->total_due * 0.5;
        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $partialAmount,
                'payment_method' => 'cash',
                'reference' => 'PARTIAL-001',
            ]);
        $response->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Partial, $invoice->status);
        $this->assertEquals($partialAmount, $invoice->amount_paid);

        $remainingAmount = $invoice->total_due - $invoice->amount_paid;
        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $remainingAmount,
                'payment_method' => 'bank_transfer',
                'reference' => 'FINAL-001',
            ]);
        $response->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);

        $receipts = Receipt::where('invoice_id', $invoice->id)->get();
        $this->assertCount(2, $receipts);
    }

    public function test_void_and_reissue_workflow(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $invoice->items()->create([
            'item_type' => 'rent',
            'description' => 'Monthly Rent',
            'quantity' => 1,
            'unit_price' => 25000,
            'total' => 25000,
        ]);

        $this->actingAs($this->landlord)
            ->post(route('invoices.void', $invoice), [
                'reason' => 'Incorrect amount',
            ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Voided, $invoice->status);
        $this->assertNotNull($invoice->voided_at);
        $this->assertEquals('Incorrect amount', $invoice->void_reason);

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.reissue', $invoice));

        $response->assertRedirect();

        $newInvoice = Invoice::where('id', '!=', $invoice->id)
            ->where('lease_id', $lease->id)
            ->latest()
            ->first();

        $this->assertNotNull($newInvoice);
        $this->assertEquals(InvoiceStatus::Draft, $newInvoice->status);
        $this->assertNotEquals($invoice->invoice_number, $newInvoice->invoice_number);
        $this->assertEquals(0, $newInvoice->amount_paid);

        $this->assertEquals($invoice->items->count(), $newInvoice->items->count());
    }

    public function test_bulk_invoice_generation(): void
    {
        $units = $this->setupData['units']->take(3);

        foreach ($units as $unit) {
            $this->createTenantWithActiveLease($this->landlord, $unit);
        }

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.generate'), [
                'month' => Carbon::now()->month,
                'year' => Carbon::now()->year,
            ]);

        $response->assertRedirect(route('invoices.index'));

        $invoiceCount = Invoice::where('landlord_id', $this->landlord->id)
            ->whereMonth('billing_period_start', Carbon::now()->month)
            ->whereYear('billing_period_start', Carbon::now()->year)
            ->count();

        $this->assertEquals(3, $invoiceCount);
    }

    public function test_overpayment_credits_to_wallet(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 5000;
        $totalPayment = $invoice->total_due + $overpaymentAmount;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
            ]);

        $invoice->refresh();
        $lease->refresh();

        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals($overpaymentAmount, $lease->wallet_balance);
    }

    public function test_send_reminder_for_unpaid_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.send-reminder', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_preview_invoice_returns_pdf(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.preview', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_download_invoice_returns_pdf(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
