<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Mail\TenantLedgerStatementMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Regression: the landlord-side "email statement" route (tenants.ledger.email) 500'd
 * with "No hint path defined for [mail]" because it sent the markdown view
 * emails.tenant-statement via Mail::send(view, ...) — which never registers the `mail`
 * component namespace. It now goes through TenantLedgerStatementMail (markdown Mailable).
 *
 * NOTE: a Mail::fake() route test does NOT render the body, so it could not have caught
 * the original bug — test_mailable_renders_markdown_body() renders it explicitly.
 */
class TenantLedgerEmailTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_landlord_can_email_a_tenant_their_ledger(): void
    {
        Mail::fake();

        $this->actingAs($this->landlord->fresh())
            ->post(route('tenants.ledger.email', $this->tenant->id))
            ->assertRedirect();

        Mail::assertSent(
            TenantLedgerStatementMail::class,
            fn (TenantLedgerStatementMail $mail) => $mail->hasTo($this->tenant->email),
        );
    }

    public function test_mailable_renders_markdown_body(): void
    {
        // Renders the <x-mail::message> markdown view — reproduces the original
        // "No hint path defined for [mail]" path that Mail::fake() route tests skip.
        $mailable = $this->makeMailable();
        $html = $mailable->render();

        $this->assertStringContainsString('Your Account Statement', $html);
        $this->assertStringContainsString($this->tenant->name, $html);
        $this->assertStringContainsString('KES', $html);
        // The <x-mail::panel> (a distinct mail component) + the positive-balance branch.
        $this->assertStringContainsString('Current Balance Due', $html);
        $this->assertStringContainsString('20,000.00', $html);
    }

    public function test_no_email_on_file_fails_with_a_clean_message_not_a_500(): void
    {
        Mail::fake();
        $this->tenant->forceFill(['email' => ''])->saveQuietly();

        $this->actingAs($this->landlord->fresh())
            ->post(route('tenants.ledger.email', $this->tenant->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_mailable_attaches_the_pdf(): void
    {
        $this->makeMailable()->assertHasAttachedData('%PDF-1.4 fake', 'statement.pdf', ['mime' => 'application/pdf']);
    }

    private function makeMailable(): TenantLedgerStatementMail
    {
        return new TenantLedgerStatementMail(
            tenant: $this->tenant,
            landlord: $this->landlord,
            summary: [
                'total_invoiced' => 50000,
                'total_paid' => 30000,
                'total_refunds' => 0,
                'current_balance' => 20000,
            ],
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            currencySymbol: 'KES',
            pdfContent: '%PDF-1.4 fake',
            pdfFilename: 'statement.pdf',
        );
    }
}
