<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Enums\Currency;
use App\Mail\CreditNoteIssued;
use App\Mail\DepositRefundNotification;
use App\Mail\InvoiceReminder;
use App\Mail\InvoiceSent;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentVerificationApproved;
use App\Mail\PaymentVerificationRejected;
use App\Mail\RentHikeNotice;
use App\Mail\TenantCredentials;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantWelcome;
use App\Models\Building;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Tests\TestCase;

class EmailCurrencySymbolTest extends TestCase
{
    use RefreshDatabase;

    private function createUsdBuilding(): Building
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        return Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::USD)
            ->create();
    }

    private function createUsdLeaseWithTenant(Building $building): array
    {
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::find($lease->tenant_id);

        return [$lease, $tenant, $unit];
    }

    public function test_invoice_sent_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $mailable = new InvoiceSent($invoice);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_invoice_reminder_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $mailable = new InvoiceReminder($invoice);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_rent_hike_notice_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease] = $this->createUsdLeaseWithTenant($building);

        $mailable = new RentHikeNotice($lease, 25000, 30000, 'March 1, 2026');

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_deposit_refund_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease] = $this->createUsdLeaseWithTenant($building);
        $lease->update([
            'deposit_refund_amount' => 20000,
            'deposit_deductions' => 5000,
            'deposit_deduction_reason' => 'Cleaning fee',
        ]);

        $mailable = new DepositRefundNotification($lease->fresh(), 'partial_refund');

        $mailable->assertDontSeeInHtml('KES ');
        $mailable->assertDontSeeInHtml('(KES)');
    }

    public function test_tenant_welcome_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $tenant = User::find($lease->tenant_id);
        $invitation = TenantInvitation::factory()
            ->forUnit($unit)
            ->create(['landlord_id' => $building->landlord_id]);

        $mailable = new TenantWelcome($tenant, $invitation, $lease);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_tenant_invitation_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $invitation = TenantInvitation::factory()
            ->forUnit($unit)
            ->create(['landlord_id' => $building->landlord_id]);

        $mailable = new TenantInvitationMail($invitation);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_tenant_statement_uses_dynamic_currency(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $mailable = new class($tenant, $landlord) extends Mailable
        {
            public function __construct(
                private User $tenant,
                private User $landlord,
            ) {}

            public function content(): Content
            {
                return new Content(
                    markdown: 'emails.tenant-statement',
                    with: [
                        'tenant' => $this->tenant,
                        'landlord' => $this->landlord,
                        'summary' => [
                            'total_invoiced' => 50000,
                            'total_paid' => 30000,
                            'total_refunds' => 0,
                            'current_balance' => 20000,
                        ],
                        'dateFrom' => '2026-01-01',
                        'dateTo' => '2026-01-31',
                        'currency_symbol' => Currency::USD->symbol(),
                    ],
                );
            }
        };

        $mailable->assertDontSeeInHtml('KES ');
        $mailable->assertSeeInHtml('$ ');
    }

    public function test_overpayment_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease, $tenant, $unit] = $this->createUsdLeaseWithTenant($building);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'amount' => 30000,
            'currency' => Currency::USD,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $mailable = new OverpaymentNotification($payment, $lease, $tenant, 500, 500);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_credit_note_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $tenant = User::find($lease->tenant_id);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();
        $creditNote = CreditNote::create([
            'landlord_id' => $building->landlord_id,
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'credit_number' => 'CN-'.uniqid(),
            'amount' => 5000,
            'applied_amount' => 0,
            'reason' => CreditNote::REASON_OVERPAYMENT,
            'status' => CreditNote::STATUS_APPROVED,
        ]);

        $mailable = new CreditNoteIssued($creditNote);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_verification_approved_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease] = $this->createUsdLeaseWithTenant($building);
        $verification = TenantPaymentVerification::create([
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'amount_paid' => 50000,
            'verified_at' => now(),
        ]);

        $mailable = new PaymentVerificationApproved($verification);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_verification_rejected_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease] = $this->createUsdLeaseWithTenant($building);
        $verification = TenantPaymentVerification::create([
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'status' => TenantPaymentVerification::STATUS_REJECTED,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'rejection_reason' => 'Invalid payment proof',
        ]);

        $mailable = new PaymentVerificationRejected($verification);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_tenant_credentials_uses_dynamic_currency(): void
    {
        $building = $this->createUsdBuilding();
        [$lease, $tenant] = $this->createUsdLeaseWithTenant($building);
        $landlord = User::find($building->landlord_id);

        $mailable = new TenantCredentials($tenant, $lease, 'TempPass123!', $landlord);

        $mailable->assertDontSeeInHtml('KES ');
    }

    public function test_kes_currency_renders_symbol_not_code(): void
    {
        $invoice = Invoice::factory()->sent()->create(['currency' => 'KES']);

        $mailable = new InvoiceSent($invoice);

        $mailable->assertSeeInHtml('KSh ');
    }
}
