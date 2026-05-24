<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Payment\ReceiptGenerator;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ReceiptGeneratorTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected MockInterface $receiptService;

    protected ReceiptGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiptService = Mockery::mock(ReceiptService::class);
        $this->generator = new ReceiptGenerator($this->receiptService);
    }

    // ------------------------------------------------------------------
    // download() tests
    // ------------------------------------------------------------------

    public function test_download_creates_receipt_if_missing(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-001',
        ]);

        $fakeReceipt = Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'receipt_number' => 'RCT-TEST-0001',
            'amount' => 25000,
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        $fakeResponse = new Response('pdf-content', 200, ['Content-Type' => 'application/pdf']);

        // Remove the auto-created receipt so ensureReceipt() triggers createReceipt()
        Receipt::where('payment_id', $payment->id)->delete();
        $payment->unsetRelation('receipt');

        $this->receiptService
            ->shouldReceive('createReceipt')
            ->once()
            ->with(
                Mockery::on(fn (Payment $p) => $p->id === $payment->id),
                Mockery::on(fn (Invoice $i) => $i->id === $invoice->id),
            )
            ->andReturn($fakeReceipt);

        $this->receiptService
            ->shouldReceive('downloadPdf')
            ->once()
            ->with(Mockery::on(fn (Receipt $r) => $r->id === $fakeReceipt->id))
            ->andReturn($fakeResponse);

        $result = $this->generator->download($payment);

        $this->assertSame($fakeResponse, $result);
    }

    public function test_download_uses_existing_receipt(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-002',
        ]);

        $existingReceipt = Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'receipt_number' => 'RCT-EXISTING-0001',
            'amount' => 25000,
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        $fakeResponse = new Response('pdf-content', 200, ['Content-Type' => 'application/pdf']);

        $this->receiptService
            ->shouldNotReceive('createReceipt');

        $this->receiptService
            ->shouldReceive('downloadPdf')
            ->once()
            ->with(Mockery::on(fn (Receipt $r) => $r->id === $existingReceipt->id))
            ->andReturn($fakeResponse);

        $result = $this->generator->download($payment);

        $this->assertSame($fakeResponse, $result);
    }

    // ------------------------------------------------------------------
    // email() tests
    // ------------------------------------------------------------------

    public function test_email_sends_payment_received_mailable(): void
    {
        Mail::fake();

        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-003',
        ]);

        $receipt = Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'receipt_number' => 'RCT-EMAIL-0001',
            'amount' => 25000,
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        $this->receiptService->shouldNotReceive('createReceipt');

        $this->generator->email($payment);

        Mail::assertQueued(PaymentReceived::class, function (PaymentReceived $mail) use ($tenant) {
            return $mail->hasTo($tenant->email);
        });
    }

    public function test_email_marks_receipt_as_emailed(): void
    {
        Mail::fake();

        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-004',
        ]);

        $receipt = Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'receipt_number' => 'RCT-EMAILED-0001',
            'amount' => 25000,
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        $this->receiptService->shouldNotReceive('createReceipt');

        $this->generator->email($payment);

        $receipt->refresh();
        $this->assertNotNull($receipt->emailed_at);
    }

    public function test_email_creates_receipt_if_missing(): void
    {
        Mail::fake();

        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-005',
        ]);

        $fakeReceipt = Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'receipt_number' => 'RCT-CREATED-0001',
            'amount' => 25000,
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        // Delete the receipt so ensureReceipt triggers creation
        Receipt::where('payment_id', $payment->id)->delete();
        $payment->unsetRelation('receipt');

        $this->receiptService
            ->shouldReceive('createReceipt')
            ->once()
            ->with(
                Mockery::on(fn (Payment $p) => $p->id === $payment->id),
                Mockery::on(fn (Invoice $i) => $i->id === $invoice->id),
            )
            ->andReturn($fakeReceipt);

        $this->generator->email($payment);

        Mail::assertQueued(PaymentReceived::class);
    }

    public function test_email_throws_when_no_tenant(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setupData['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'UNIT-TEST-006',
        ]);

        // Simulate a missing payer by overriding the loaded relationship chain
        $payment->setRelation('invoice', null);

        $this->expectException(\RuntimeException::class);
        // Phase-99: the receipt resolves the payer via recipientUser() (tenant or water client).
        $this->expectExceptionMessage('Unable to send receipt - recipient not found.');

        $this->generator->email($payment);
    }

    // ------------------------------------------------------------------
    // preview() tests
    // ------------------------------------------------------------------

    public function test_preview_delegates_to_receipt_service(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $settings = $setupData['landlord']->getOrCreateInvoiceSetting();

        $fakeResponse = new Response('pdf-preview', 200, ['Content-Type' => 'application/pdf']);

        $this->receiptService
            ->shouldReceive('streamPreviewPdf')
            ->once()
            ->with($settings, null)
            ->andReturn($fakeResponse);

        $result = $this->generator->preview($settings);

        $this->assertSame($fakeResponse, $result);
    }
}
