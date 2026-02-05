<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Mail\PaymentReceived;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Payment\ManualPaymentHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManualPaymentHandlerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected ManualPaymentHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
        Mail::fake();
        Event::fake([PaymentReceivedEvent::class]);

        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_xxxxx',
            'paystack_secret_key' => 'sk_test_xxxxx',
        ]);

        $this->handler = app(ManualPaymentHandler::class);
    }

    public function test_records_payment_for_invoice_and_updates_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
        ]);

        $invoice->refresh();
        $this->assertEquals($invoice->total_due, $invoice->amount_paid);
        $this->assertInstanceOf(Payment::class, $result->payment);
        $this->assertNotNull($result->invoice);
    }

    public function test_marks_invoice_as_paid_when_fully_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_marks_invoice_as_partial_when_partially_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $partialAmount = $invoice->total_due / 2;

        $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $partialAmount,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Partial, $invoice->status);
        $this->assertEquals($partialAmount, $invoice->amount_paid);
    }

    public function test_handles_overpayment_with_wallet_credit(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 5000;
        $totalPayment = $invoice->total_due + $overpaymentAmount;

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $totalPayment,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertTrue($result->hasOverpayment());
        $this->assertEquals($overpaymentAmount, $result->overpayment);

        $lease->refresh();
        $this->assertEquals($overpaymentAmount, $lease->wallet_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'lease_id' => $lease->id,
            'amount' => $overpaymentAmount,
        ]);
    }

    public function test_overpayment_result_contains_notification_data(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 3000;

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due + $overpaymentAmount,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $notification = $result->overpaymentNotification();
        $this->assertNotNull($notification);
        $this->assertEquals($result->payment->id, $notification['payment_id']);
        $this->assertEquals($lease->id, $notification['lease_id']);
        $this->assertEquals($overpaymentAmount, $notification['overpayment']);
    }

    public function test_no_overpayment_for_exact_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertFalse($result->hasOverpayment());
        $this->assertNull($result->overpaymentNotification());
    }

    public function test_rejects_invoice_from_different_landlord(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        $otherUnit = $otherSetup['units']->first();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease($otherSetup['landlord'], $otherUnit);
        $otherInvoice = $this->createInvoiceForLease($otherLease, 'sent');

        $this->expectException(ModelNotFoundException::class);

        $this->handler->record($this->landlord->id, [
            'invoice_id' => $otherInvoice->id,
            'tenant_id' => null,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);
    }

    public function test_dispatches_payment_received_event(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        Event::assertDispatched(PaymentReceivedEvent::class);
    }

    public function test_queues_payment_received_email(): void
    {
        Event::fake([PaymentReceivedEvent::class]);

        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        Mail::assertQueued(PaymentReceived::class);
    }

    public function test_creates_receipt_via_receipt_service(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $result->payment->id,
            'invoice_id' => $invoice->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_generates_manual_reference_when_not_provided(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertStringStartsWith('MANUAL-', $result->payment->reference);
    }

    public function test_uses_provided_reference_when_given(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->handler->record($this->landlord->id, [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'reference' => 'CUSTOM-REF-001',
        ]);

        $this->assertEquals('CUSTOM-REF-001', $result->payment->reference);
    }
}
