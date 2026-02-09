<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptTemplate;
use App\Services\PaymentQrCodeService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ReceiptServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected ReceiptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $qrCodeService = Mockery::mock(PaymentQrCodeService::class);
        $qrCodeService->shouldReceive('generateReceiptQrCode')->andReturn('data:image/png;base64,fake');
        $this->service = new ReceiptService($qrCodeService);
    }

    // ------------------------------------------------------------------
    // createReceipt() tests
    // ------------------------------------------------------------------

    #[Test]
    public function create_receipt_stores_record_with_correct_attributes(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'partial');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 15000,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-001',
            'notes' => 'Test receipt',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertTrue($receipt->exists);
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame($invoice->id, $receipt->invoice_id);
        $this->assertSame($lease->id, $receipt->lease_id);
        $this->assertSame($setup['landlord']->id, $receipt->landlord_id);
        $this->assertEquals(15000, $receipt->amount);
        $this->assertSame('mpesa', $receipt->payment_method);
        $this->assertSame('RCPT-SVC-001', $receipt->reference);
        $this->assertNotNull($receipt->receipt_number);
        $this->assertNotNull($receipt->issued_at);
    }

    #[Test]
    public function create_receipt_uses_landlord_invoice_setting_for_number(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        InvoiceSetting::create([
            'landlord_id' => $setup['landlord']->id,
            'receipt_prefix' => 'CUST',
            'receipt_next_number' => 42,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-002',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $expectedPrefix = 'CUST-'.now()->format('Ym').'-0042';
        $this->assertSame($expectedPrefix, $receipt->receipt_number);
    }

    #[Test]
    public function create_receipt_uses_fallback_number_when_no_settings(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-003',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $expectedPattern = '/^RCT-'.date('Ym').'-\d{4}$/';
        $this->assertMatchesRegularExpression($expectedPattern, $receipt->receipt_number);
    }

    #[Test]
    public function create_receipt_marks_partial_when_amount_paid_less_than_total(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);

        $invoice = $this->createInvoiceForLease($lease, 'partial');
        $invoice->update(['amount_paid' => 5000, 'total_due' => 25000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-004',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $this->assertTrue($receipt->is_partial);
    }

    #[Test]
    public function create_receipt_marks_full_when_fully_paid(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);

        $invoice = $this->createInvoiceForLease($lease, 'paid');
        $invoice->update(['amount_paid' => 25000, 'total_due' => 25000]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-005',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $this->assertFalse($receipt->is_partial);
    }

    #[Test]
    public function create_receipt_without_invoice(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);

        $payment = Payment::create([
            'invoice_id' => null,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-006',
        ]);

        $receipt = $this->service->createReceipt($payment);

        $this->assertNull($receipt->invoice_id);
        $this->assertFalse($receipt->is_partial);
    }

    #[Test]
    public function fallback_receipt_number_increments_correctly(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice1 = $this->createInvoiceForLease($lease, 'paid');

        $payment1 = Payment::create([
            'invoice_id' => $invoice1->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-007A',
        ]);

        $receipt1 = $this->service->createReceipt($payment1, $invoice1);

        $invoice2 = $this->createInvoiceForLease($lease, 'paid');

        $payment2 = Payment::create([
            'invoice_id' => $invoice2->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-007B',
        ]);

        $receipt2 = $this->service->createReceipt($payment2, $invoice2);

        $ym = date('Ym');
        $this->assertSame("RCT-{$ym}-0001", $receipt1->receipt_number);
        $this->assertSame("RCT-{$ym}-0002", $receipt2->receipt_number);
    }

    // ------------------------------------------------------------------
    // generatePdf() tests
    // ------------------------------------------------------------------

    #[Test]
    public function generate_pdf_stores_file_and_updates_receipt(): void
    {
        Storage::fake('private');

        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-PDF-001',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class)->makePartial();
        $pdfMock->shouldReceive('loadView')
            ->once()
            ->with('receipts.templated-receipt', Mockery::type('array'))
            ->andReturnSelf();
        $pdfMock->shouldReceive('output')->once()->andReturn('%PDF-1.4 fake content');
        $this->app->bind('dompdf.wrapper', fn () => $pdfMock);

        $path = $this->service->generatePdf($receipt);

        $expectedPath = "receipts/{$setup['landlord']->id}/{$receipt->receipt_number}.pdf";
        $this->assertSame($expectedPath, $path);
        Storage::disk('private')->assertExists($expectedPath);

        $receipt->refresh();
        $this->assertSame($expectedPath, $receipt->pdf_path);
    }

    #[Test]
    public function generate_pdf_uses_default_template_when_none_provided(): void
    {
        Storage::fake('private');

        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-PDF-002',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $capturedData = null;
        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class)->makePartial();
        $pdfMock->shouldReceive('loadView')
            ->once()
            ->with('receipts.templated-receipt', Mockery::on(function ($data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            }))
            ->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('%PDF-1.4');
        $this->app->bind('dompdf.wrapper', fn () => $pdfMock);

        $this->service->generatePdf($receipt);

        $this->assertNotNull($capturedData);
        $template = $capturedData['template'];
        $this->assertSame(ReceiptTemplate::DESIGN_CLASSIC, $template->design);
        $this->assertSame('#059669', $template->primary_color);
    }

    #[Test]
    public function stream_pdf_returns_streamed_response(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-STREAM-001',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $fakeResponse = response('pdf-stream');
        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class)->makePartial();
        $pdfMock->shouldReceive('loadView')
            ->once()
            ->with('receipts.templated-receipt', Mockery::type('array'))
            ->andReturnSelf();
        $pdfMock->shouldReceive('stream')
            ->once()
            ->with("Receipt-{$receipt->receipt_number}.pdf")
            ->andReturn($fakeResponse);
        $this->app->bind('dompdf.wrapper', fn () => $pdfMock);

        $result = $this->service->streamPdf($receipt);

        $this->assertSame($fakeResponse, $result);
    }

    #[Test]
    public function download_pdf_returns_download_response(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($setup['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'RCPT-SVC-DL-001',
        ]);

        $receipt = $this->service->createReceipt($payment, $invoice);

        $fakeResponse = response()->make('pdf-download', 200, ['Content-Disposition' => 'attachment']);
        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class)->makePartial();
        $pdfMock->shouldReceive('loadView')
            ->once()
            ->with('receipts.templated-receipt', Mockery::type('array'))
            ->andReturnSelf();
        $pdfMock->shouldReceive('download')
            ->once()
            ->with("Receipt-{$receipt->receipt_number}.pdf")
            ->andReturn($fakeResponse);
        $this->app->bind('dompdf.wrapper', fn () => $pdfMock);

        $result = $this->service->downloadPdf($receipt);

        $this->assertSame($fakeResponse, $result);
    }

    #[Test]
    public function stream_preview_pdf_uses_sample_data(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $settings = $setup['landlord']->getOrCreateInvoiceSetting();

        $capturedData = null;
        $fakeResponse = response('preview-pdf');
        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class)->makePartial();
        $pdfMock->shouldReceive('loadView')
            ->once()
            ->with('receipts.templated-receipt', Mockery::on(function ($data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            }))
            ->andReturnSelf();
        $pdfMock->shouldReceive('stream')
            ->once()
            ->with('receipt-preview.pdf')
            ->andReturn($fakeResponse);
        $this->app->bind('dompdf.wrapper', fn () => $pdfMock);

        $result = $this->service->streamPreviewPdf($settings);

        $this->assertSame($fakeResponse, $result);
        $this->assertNotNull($capturedData);
        $this->assertSame('RCT-PREVIEW-0001', $capturedData['receipt']->receipt_number);
        $this->assertSame('PAY-PREVIEW-0001', $capturedData['payment']->reference);
        $this->assertSame('John Doe', $capturedData['tenant']->name);
    }
}
