<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Enums\Currency;
use App\Mail\InvoiceReminder;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Mail\PaymentVerificationApproved;
use App\Mail\PaymentVerificationRejected;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailFooterConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function createBuilding(): Building
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        return Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
    }

    private function createLeaseWithTenant(Building $building): array
    {
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        return [$lease, $tenant, $unit];
    }

    public function test_payment_received_contains_unsubscribe_link(): void
    {
        $building = $this->createBuilding();
        [$lease, $tenant, $unit] = $this->createLeaseWithTenant($building);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'amount' => 25000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $mailable = new PaymentReceived($payment, $invoice);

        $mailable->assertSeeInHtml('Manage email preferences');
    }

    public function test_invoice_reminder_contains_unsubscribe_link(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();

        $mailable = new InvoiceReminder($invoice);

        $mailable->assertSeeInHtml('Manage email preferences');
    }

    public function test_payment_verification_approved_contains_unsubscribe_link(): void
    {
        $building = $this->createBuilding();
        [$lease] = $this->createLeaseWithTenant($building);
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

        $mailable->assertSeeInHtml('Manage email preferences');
    }

    public function test_payment_verification_rejected_contains_unsubscribe_link(): void
    {
        $building = $this->createBuilding();
        [$lease] = $this->createLeaseWithTenant($building);
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

        $mailable->assertSeeInHtml('Manage email preferences');
    }

    public function test_overpayment_notification_contains_preferences_link(): void
    {
        $building = $this->createBuilding();
        [$lease, $tenant, $unit] = $this->createLeaseWithTenant($building);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'amount' => 50000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $mailable = new OverpaymentNotification($payment, $lease, $tenant, 5000, 5000);

        $mailable->assertSeeInHtml('Manage email preferences');
    }

    public function test_all_payment_emails_have_consistent_team_footer(): void
    {
        $building = $this->createBuilding();
        [$lease, $tenant, $unit] = $this->createLeaseWithTenant($building);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'amount' => 25000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $appName = config('app.name');

        $mailables = [
            new PaymentReceived($payment, $invoice),
            new InvoiceReminder($invoice),
            new OverpaymentNotification($payment, $lease, $tenant, 500, 500),
        ];

        foreach ($mailables as $mailable) {
            $mailable->assertSeeInHtml($appName.' Team');
        }
    }

    public function test_payment_received_unsubscribe_url_is_signed(): void
    {
        $building = $this->createBuilding();
        [$lease, $tenant, $unit] = $this->createLeaseWithTenant($building);
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'amount' => 25000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $mailable = new PaymentReceived($payment, $invoice);

        $mailable->assertSeeInHtml('signature=');
    }
}
