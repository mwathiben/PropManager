<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Mail\PaymentReceived;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\ReceiptGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ReceiptGeneratorTest extends TestCase
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

    // ------------------------------------------------------------------
    // Integration: download()
    // ------------------------------------------------------------------

    public function test_download_generates_pdf_response(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'FEAT-TEST-001',
        ]);

        $generator = app(ReceiptGenerator::class);
        $response = $generator->download($payment);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_receipt_record_created_for_legacy_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'FEAT-TEST-002',
        ]);

        $this->assertDatabaseMissing('receipts', ['payment_id' => $payment->id]);

        $generator = app(ReceiptGenerator::class);
        $generator->download($payment);

        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id]);
    }

    public function test_email_sends_payment_received_mailable(): void
    {
        Mail::fake();

        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'FEAT-TEST-003',
        ]);

        $generator = app(ReceiptGenerator::class);
        $generator->email($payment);

        Mail::assertQueued(PaymentReceived::class, function (PaymentReceived $mail) use ($tenant) {
            return $mail->hasTo($tenant->email);
        });
    }

    // ------------------------------------------------------------------
    // Integration: Controller endpoints
    // ------------------------------------------------------------------

    public function test_controller_download_receipt_creates_receipt_record(): void
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
            'reference' => 'FEAT-CTRL-001',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('payments.downloadReceipt', $payment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id]);
    }

    public function test_controller_send_receipt_returns_success(): void
    {
        Mail::fake();

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
            'reference' => 'FEAT-CTRL-002',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('payments.send-receipt', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Receipt sent successfully.');

        Mail::assertQueued(PaymentReceived::class);
    }

    public function test_api_tenant_receipt_uses_generator(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'reference' => 'FEAT-API-001',
        ]);

        Sanctum::actingAs($tenant, ['tenant:read']);

        $response = $this->get('/api/v1/tenant/payments/'.$payment->id.'/receipt');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id]);
    }

    public function test_preview_receipt_streams_pdf(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('finances.settings.receipt.preview'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
