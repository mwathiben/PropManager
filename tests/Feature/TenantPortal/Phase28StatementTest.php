<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Mail\TenantStatementMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Tenant\StatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-STATEMENT-1/2/3 watchdog suite.
 */
class Phase28StatementTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_running_balance_walks_chronologically_and_matches_charges_minus_payments(): void
    {
        $this->seedActivity();

        $rows = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
        );

        $invoiceRows = $rows->where('kind', 'invoice')->values();
        $paymentRows = $rows->where('kind', 'payment')->values();

        $this->assertCount(3, $invoiceRows, 'expected three invoice rows within the period');
        $this->assertCount(2, $paymentRows, 'expected two payment rows within the period');

        $closing = $rows->where('kind', 'closing')->first();
        $opening = $rows->where('kind', 'opening')->first();

        $totalCharges = $invoiceRows->sum('charge');
        $totalPayments = $paymentRows->sum('payment');

        $this->assertEqualsWithDelta(
            $opening['running_balance'] + $totalCharges - $totalPayments,
            $closing['running_balance'],
            0.01,
        );
    }

    public function test_opening_balance_reflects_pre_period_activity(): void
    {
        Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-PRE-1',
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'billing_period_start' => '2025-12-01',
            'due_date' => '2025-12-07',
        ]);

        $rows = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-31'),
        );

        $opening = $rows->where('kind', 'opening')->first();
        $this->assertEqualsWithDelta(25000.0, $opening['running_balance'], 0.01);
    }

    public function test_voided_invoices_and_payments_are_excluded(): void
    {
        Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-VOID-1',
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'voided',
            'billing_period_start' => '2026-02-01',
            'due_date' => '2026-02-07',
            'voided_at' => now(),
        ]);

        Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => '2026-02-15',
            'is_voided' => true,
            'voided_at' => now(),
        ]);

        $rows = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-02-01'),
            CarbonImmutable::parse('2026-02-28'),
        );

        $this->assertSame(0, $rows->where('kind', 'invoice')->count());
        $this->assertSame(0, $rows->where('kind', 'payment')->count());
    }

    public function test_index_route_renders_statement_page(): void
    {
        $this->seedActivity();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.index', ['period' => 'year_to_date']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Statement')
            ->where('period', 'year_to_date')
            ->has('rows')
            ->has('allowedPeriods')
        );
    }

    public function test_xlsx_export_returns_xlsx_mime(): void
    {
        $this->seedActivity();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.xlsx', ['period' => 'year_to_date']));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_email_queues_mailable_only_to_authenticated_tenant(): void
    {
        Mail::fake();

        $this->actingAs($this->tenant)
            ->post(route('tenant.statement.email'), ['period' => 'current_month'])
            ->assertRedirect();

        Mail::assertQueued(
            TenantStatementMail::class,
            fn (TenantStatementMail $mail) => $mail->hasTo($this->tenant->email)
                && ! $mail->hasTo($this->landlord->email),
        );
    }

    public function test_unknown_period_falls_back_to_current_month_without_error(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.index', ['period' => 'lifetime; DROP TABLE users']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('period', 'current_month'));
    }

    public function test_landlord_cannot_reach_tenant_statement(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('tenant.statement.index'));
        $response->assertForbidden();
    }

    private function seedActivity(): void
    {
        $invoiceDates = ['2026-01-05', '2026-02-05', '2026-03-05'];
        foreach ($invoiceDates as $i => $date) {
            Invoice::create([
                'lease_id' => $this->lease->id,
                'landlord_id' => $this->landlord->id,
                'invoice_number' => 'INV-2026-'.($i + 1),
                'rent_due' => 25000,
                'water_due' => 0,
                'arrears' => 0,
                'wallet_applied' => 0,
                'total_due' => 25000,
                'amount_paid' => 0,
                'status' => 'sent',
                'billing_period_start' => $date,
                'due_date' => $date,
            ]);
        }

        Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => '2026-01-10',
            'reference' => 'MPESA-001',
            'is_voided' => false,
        ]);

        Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => '2026-02-10',
            'reference' => 'MPESA-002',
            'is_voided' => false,
        ]);
    }
}
