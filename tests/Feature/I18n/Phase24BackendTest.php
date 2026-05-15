<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Mail\InvoiceSent;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Phase-24 I18N-BACKEND-1 watchdog. Mailable subjects must come from
 * the lang bundle (not be hardcoded English), and the User model must
 * implement HasLocalePreference so queued mail auto-localises to the
 * recipient's chosen language with no send-site wiring.
 */
class Phase24BackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_implements_has_locale_preference(): void
    {
        $user = User::factory()->create(['locale' => 'sw']);

        $this->assertInstanceOf(
            HasLocalePreference::class,
            $user,
            'I18N-BACKEND-1: User must implement HasLocalePreference so Mail::to($user) auto-localises.',
        );

        $this->assertSame('sw', $user->preferredLocale());
    }

    public function test_email_subject_keys_exist_in_every_locale(): void
    {
        $required = [
            'invoice_sent',
            'payment_received',
            'invoice_reminder',
            'invoice_overdue',
            'credit_note_issued',
            'data_export_ready',
            'deposit_refunded',
            'deposit_partial_refund',
            'deposit_forfeited',
            'deposit_update',
            'landlord_welcome',
            'overpayment_notice',
            'payment_verification_approved',
            'payment_verification_rejected',
            'rent_hike_notice',
            'tenant_credentials',
            'tenant_welcome',
            'tenant_invitation_existing',
            'tenant_invitation_new',
            'caretaker_invitation',
        ];

        foreach (array_keys(config('app.available_locales')) as $locale) {
            $bundle = require lang_path("{$locale}/emails.php");
            $subjects = $bundle['subjects'] ?? [];

            foreach ($required as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $subjects,
                    "I18N-BACKEND-1: lang/{$locale}/emails.php must define subjects.{$key}.",
                );
            }
        }
    }

    public function test_invoice_sent_subject_renders_in_swahili(): void
    {
        $user = User::factory()->create(['locale' => 'sw']);

        // Render the envelope under the recipient's locale and confirm
        // the subject is translated — not the English literal.
        App::setLocale('sw');

        $invoice = $this->makeInvoice($user);
        $envelope = (new InvoiceSent($invoice))->envelope();

        $this->assertStringContainsString(
            'Ankara',
            $envelope->subject,
            'I18N-BACKEND-1: InvoiceSent subject must use the localised template.',
        );
        $this->assertStringContainsString(
            $invoice->invoice_number,
            $envelope->subject,
            'I18N-BACKEND-1: subject must still interpolate the invoice number.',
        );
    }

    public function test_user_facing_mailable_subjects_use_translation_keys(): void
    {
        // Source-level assertion — every user-facing mailable must
        // call __('emails.subjects.*') rather than embed a literal
        // English subject. This is the cheap watchdog that catches a
        // future contributor adding `subject: 'Hardcoded English'`.
        $mailables = [
            'CaretakerInvitation.php',
            'CreditNoteIssued.php',
            'DataExportReady.php',
            'DepositRefundNotification.php',
            'InvoiceReminder.php',
            'InvoiceSent.php',
            'LandlordWelcome.php',
            'OverpaymentNotification.php',
            'PaymentReceived.php',
            'PaymentVerificationApproved.php',
            'PaymentVerificationRejected.php',
            'RentHikeNotice.php',
            'TenantCredentials.php',
            'TenantInvitationMail.php',
            'TenantWelcome.php',
        ];

        foreach ($mailables as $file) {
            $source = file_get_contents(app_path("Mail/{$file}"));

            $this->assertMatchesRegularExpression(
                "/['\"]emails\\.subjects\\.[a-z_]+['\"]/",
                $source,
                "I18N-BACKEND-1: {$file} must reference an emails.subjects.* translation key.",
            );
            $this->assertStringContainsString(
                '__(',
                $source,
                "I18N-BACKEND-1: {$file} must use __() to resolve its subject translation.",
            );
        }
    }

    private function makeInvoice(User $tenant): Invoice
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create(['property_id' => $property->id]);
        $unit = Unit::factory()->create(['building_id' => $building->id]);
        $lease = Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        return Invoice::factory()->create([
            'lease_id' => $lease->id,
            'invoice_number' => 'INV-TEST-001',
        ]);
    }
}
