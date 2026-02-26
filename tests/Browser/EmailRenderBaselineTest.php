<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\Currency;
use App\Mail\CaretakerInvitation;
use App\Mail\CreditNoteIssued;
use App\Mail\DataExportReady;
use App\Mail\DepositRefundNotification;
use App\Mail\FailedWebhookAlert;
use App\Mail\InvoiceReminder;
use App\Mail\InvoiceSent;
use App\Mail\LandlordWelcome;
use App\Mail\NotificationMail;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Mail\PaymentVerificationApproved;
use App\Mail\PaymentVerificationRejected;
use App\Mail\ReconciliationAlert;
use App\Mail\RentHikeNotice;
use App\Mail\TenantCredentials;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantWelcome;
use App\Models\Building;
use App\Models\CreditNote;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReconciliationReport;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\InteractsWithMailpit;

class EmailRenderBaselineTest extends DuskTestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
    }

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

    private function createPaymentVerification(Building $building, string $status): TenantPaymentVerification
    {
        [$lease] = $this->createLeaseWithTenant($building);

        $attributes = [
            'lease_id' => $lease->id,
            'landlord_id' => $building->landlord_id,
            'status' => $status,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
        ];

        if ($status === TenantPaymentVerification::STATUS_PAYMENT_VERIFIED) {
            $attributes['amount_paid'] = 50000;
            $attributes['verified_at'] = now();
        }

        if ($status === TenantPaymentVerification::STATUS_REJECTED) {
            $attributes['rejection_reason'] = 'Invalid payment proof';
        }

        return TenantPaymentVerification::create($attributes);
    }

    private function assertRendersAndScreenshot(Browser $browser, Mailable $mailable, string $name): void
    {
        $html = $mailable->render();

        $this->assertStringContainsString('wrapper', $html, "{$name}: Missing vendor layout wrapper class");
        $this->assertStringContainsString(config('app.name'), $html, "{$name}: Missing app name in rendered HTML");

        $this->screenshotMailableRender($browser, $mailable, $name);

        $this->assertFileExists(base_path("e2e-screenshots/emails/{$name}.png"), "{$name}: Screenshot not created");
    }

    public function test_caretaker_invitation_renders(): void
    {
        $invitation = Invitation::factory()->create();

        $mailable = new CaretakerInvitation($invitation);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'caretaker-invitation');
        });
    }

    public function test_landlord_welcome_renders(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $mailable = new LandlordWelcome($landlord);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'landlord-welcome');
        });
    }

    public function test_data_export_ready_renders(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $mailable = new DataExportReady($user, '/exports/test-export.zip');

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'data-export-ready');
        });
    }

    public function test_failed_webhook_alert_renders(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $deadLetter = WebhookDeadLetter::factory()->mpesa()->forLandlord($landlord)->create();

        $mailable = new FailedWebhookAlert($deadLetter);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'failed-webhook-alert');
        });
    }

    public function test_reconciliation_alert_renders(): void
    {
        $report = ReconciliationReport::factory()->withDiscrepancies(3)->create();

        $mailable = new ReconciliationAlert($report);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'reconciliation-alert');
        });
    }

    public function test_invoice_sent_renders(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();

        $mailable = new InvoiceSent($invoice);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'invoice-sent');
        });
    }

    public function test_rent_hike_notice_renders(): void
    {
        $building = $this->createBuilding();
        [$lease] = $this->createLeaseWithTenant($building);

        $mailable = new RentHikeNotice($lease, 25000, 30000, 'March 1, 2026');

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'rent-hike-notice');
        });
    }

    public function test_deposit_refund_notification_renders(): void
    {
        $building = $this->createBuilding();
        [$lease] = $this->createLeaseWithTenant($building);
        $lease->update([
            'deposit_refund_amount' => 20000,
            'deposit_deductions' => 5000,
            'deposit_deduction_reason' => 'Cleaning fee',
        ]);

        $mailable = new DepositRefundNotification($lease->fresh(), 'partial_refund');

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'deposit-refund-notification');
        });
    }

    public function test_tenant_welcome_renders(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $tenant = User::findOrFail($lease->tenant_id);
        $invitation = TenantInvitation::factory()
            ->forUnit($unit)
            ->create(['landlord_id' => $building->landlord_id]);

        $mailable = new TenantWelcome($tenant, $invitation, $lease);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'tenant-welcome');
        });
    }

    public function test_tenant_invitation_mail_renders(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $invitation = TenantInvitation::factory()
            ->forUnit($unit)
            ->create(['landlord_id' => $building->landlord_id]);

        $mailable = new TenantInvitationMail($invitation);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'tenant-invitation-mail');
        });
    }

    public function test_credit_note_issued_renders(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $tenant = User::findOrFail($lease->tenant_id);
        $invoice = Invoice::factory()
            ->forLease($lease)
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

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'credit-note-issued');
        });
    }

    public function test_payment_received_renders(): void
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

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'payment-received');
        });
    }

    public function test_invoice_reminder_renders(): void
    {
        $building = $this->createBuilding();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();

        $mailable = new InvoiceReminder($invoice);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'invoice-reminder');
        });
    }

    public function test_payment_verification_approved_renders(): void
    {
        $building = $this->createBuilding();
        $verification = $this->createPaymentVerification($building, TenantPaymentVerification::STATUS_PAYMENT_VERIFIED);

        $mailable = new PaymentVerificationApproved($verification);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'payment-verification-approved');
        });
    }

    public function test_payment_verification_rejected_renders(): void
    {
        $building = $this->createBuilding();
        $verification = $this->createPaymentVerification($building, TenantPaymentVerification::STATUS_REJECTED);

        $mailable = new PaymentVerificationRejected($verification);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'payment-verification-rejected');
        });
    }

    public function test_overpayment_notification_renders(): void
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
            'amount' => 30000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $mailable = new OverpaymentNotification($payment, $lease, $tenant, 5000, 5000);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'overpayment-notification');
        });
    }

    public function test_tenant_credentials_renders(): void
    {
        $building = $this->createBuilding();
        [$lease, $tenant] = $this->createLeaseWithTenant($building);
        $landlord = User::findOrFail($building->landlord_id);

        $mailable = new TenantCredentials($tenant, $lease, 'TempPass123!', $landlord);

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'tenant-credentials');
        });
    }

    public function test_notification_mail_renders(): void
    {
        $recipient = User::factory()->create(['role' => 'tenant']);

        $mailable = new NotificationMail(
            notificationSubject: 'Test Notification Subject',
            notificationMessage: 'This is a test notification message body.',
            data: ['unit_number' => 'A-101', 'rent_amount' => '25,000 KES'],
            recipient: $recipient,
        );

        $this->browse(function (Browser $browser) use ($mailable) {
            $this->assertRendersAndScreenshot($browser, $mailable, 'notification-mail');
        });
    }
}
