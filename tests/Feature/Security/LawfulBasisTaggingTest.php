<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\TenantKycSubmission;
use Database\Factories\PaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-13 DPA-3 regression coverage. Section 30 of the Kenya DPA /
 * Article 6 of GDPR require a documented lawful basis for every
 * processing operation. The Auditable trait now stamps the basis
 * into every audit row's metadata.column.
 *
 * Tests lock in:
 *   - default basis is 'legitimate_interests' (the unmapped fallback)
 *   - Lease + Invoice declare 'contract'
 *   - Payment declares 'legal_obligation' (tax retention dominates)
 *   - TenantKycSubmission declares 'legal_obligation' (AML/CFT)
 *   - audit rows for create/update events carry the basis inline
 */
class LawfulBasisTaggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The trait suppresses non-whitelisted event types — but the
        // baseline whitelist excludes 'created'/'updated'/'deleted'.
        // For DPA-3 regression we want every audit emission, so we
        // disable the whitelist gate. log_in_console is required
        // because php artisan test runs under console.
        config([
            'security.audit.logged_events' => [],
            'security.audit.log_in_console' => true,
        ]);
    }

    public function test_lease_declares_contract_basis(): void
    {
        $lease = new Lease;
        $this->assertSame('contract', $lease->getLawfulBasis());
    }

    public function test_invoice_declares_contract_basis(): void
    {
        $invoice = new Invoice;
        $this->assertSame('contract', $invoice->getLawfulBasis());
    }

    public function test_payment_declares_legal_obligation_basis(): void
    {
        $payment = new Payment;
        $this->assertSame('legal_obligation', $payment->getLawfulBasis());
    }

    public function test_kyc_declares_legal_obligation_basis(): void
    {
        $kyc = new TenantKycSubmission;
        $this->assertSame('legal_obligation', $kyc->getLawfulBasis());
    }

    public function test_audit_row_for_lease_create_carries_contract_basis(): void
    {
        $lease = Lease::factory()->create();

        $audit = AuditLog::where('auditable_type', Lease::class)
            ->where('auditable_id', $lease->id)
            ->where('event_type', AuditLog::EVENT_CREATED)
            ->latest()
            ->first();

        $this->assertNotNull($audit, 'Auditable trait must record a creation audit row');
        $this->assertSame('contract', $audit->metadata['lawful_basis']);
        $this->assertSame('kenya_dpa_section_30', $audit->metadata['compliance']);
    }

    public function test_audit_row_for_payment_carries_legal_obligation_basis(): void
    {
        $payment = PaymentFactory::new()->create();

        $audit = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('event_type', AuditLog::EVENT_CREATED)
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('legal_obligation', $audit->metadata['lawful_basis']);
    }
}
