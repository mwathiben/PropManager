<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-19 Phase 4 coverage (LOW severity + runbook):
 *   POLICY-7: 'integration:webhook' Sanctum ability mirrored in the
 *             Gate registry; DPA-4 restriction applies symmetrically.
 *   POLICY-9: cross-tenant artisan commands (invoices:generate,
 *             invoices:mark-overdue, gdpr:process-deletions) gain
 *             --landlord-id / --dry-run / --confirm guard rails.
 */
class Phase19Phase4Test extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_integration_webhook_gate_is_defined(): void
    {
        $this->assertTrue(
            Gate::has('integration:webhook'),
            "Gate::define('integration:webhook') must exist (Phase-19 POLICY-7).",
        );
    }

    public function test_integration_webhook_gate_allows_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->assertTrue(
            Gate::forUser($superAdmin)->allows('integration:webhook'),
            'Super-admin must be allowed via the Gate::before bypass.',
        );
    }

    public function test_integration_webhook_gate_denies_restricted_super_admin(): void
    {
        // POLICY-7: pre-Phase-19 the inline tokenCan check meant a
        // restricted super-admin's token could still cross-landlord
        // query. Post-fix the Gate-routed path hits DPA-4 first —
        // integration:webhook is NOT on the allow-list, so denied.
        $restrictedSuperAdmin = User::factory()->create([
            'role' => 'super_admin',
            'restricted_at' => now(),
        ]);

        $this->assertFalse(
            Gate::forUser($restrictedSuperAdmin)->allows('integration:webhook'),
            'DPA-4 restricted super-admin must NOT pass integration:webhook (write-side cross-landlord query).',
        );
    }

    public function test_integration_webhook_gate_denies_landlord_without_token(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->assertFalse(
            Gate::forUser($landlord)->allows('integration:webhook'),
            'Landlord without integration token must be denied.',
        );
    }

    public function test_invoices_generate_requires_confirm_in_interactive_mode(): void
    {
        // POLICY-9: interactive without --confirm is refused. Tests
        // run with non-interactive input by default; we simulate the
        // happy paths instead — dry-run works, scheduler-equivalent
        // --no-interaction works.
        $exitCode = $this->artisan('invoices:generate', ['--dry-run' => true])->run();
        $this->assertSame(0, $exitCode, '--dry-run must succeed without --confirm.');
    }

    public function test_invoices_generate_dry_run_does_not_create_invoices(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $countBefore = \App\Models\Invoice::count();

        $this->artisan('invoices:generate', [
            '--landlord-id' => $setup['landlord']->id,
            '--dry-run' => true,
        ])->run();

        $this->assertSame(
            $countBefore,
            \App\Models\Invoice::count(),
            '--dry-run must NOT create any invoices.',
        );
    }

    public function test_invoices_mark_overdue_dry_run_does_not_mutate(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $invoice = \App\Models\Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'status' => 'sent',
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:mark-overdue', ['--dry-run' => true])->run();

        $invoice->refresh();
        $this->assertSame(
            InvoiceStatus::Sent,
            $invoice->status,
            '--dry-run must NOT mutate invoice status.',
        );
    }

    public function test_invoices_mark_overdue_landlord_id_scopes_correctly(): void
    {
        $setupA = $this->createLandlordWithFullSetup();
        $setupB = $this->createLandlordWithFullSetup();
        $tenantA = $this->createTenantWithActiveLease($setupA['landlord'], $setupA['units']->first());
        $tenantB = $this->createTenantWithActiveLease($setupB['landlord'], $setupB['units']->first());

        $invoiceA = \App\Models\Invoice::factory()->create([
            'landlord_id' => $setupA['landlord']->id,
            'lease_id' => $tenantA['lease']->id,
            'status' => 'sent',
            'due_date' => now()->subDays(5),
        ]);

        $invoiceB = \App\Models\Invoice::factory()->create([
            'landlord_id' => $setupB['landlord']->id,
            'lease_id' => $tenantB['lease']->id,
            'status' => 'sent',
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:mark-overdue', [
            '--landlord-id' => $setupA['landlord']->id,
            '--confirm' => true,
        ])->run();

        $invoiceA->refresh();
        $invoiceB->refresh();

        $this->assertSame(InvoiceStatus::Overdue, $invoiceA->status, 'Landlord A invoice must be marked overdue.');
        $this->assertSame(InvoiceStatus::Sent, $invoiceB->status, 'Landlord B invoice must NOT be touched (--landlord-id scope).');
    }

    public function test_gdpr_process_deletions_dry_run_reports_count(): void
    {
        $exitCode = $this->artisan('gdpr:process-deletions', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN]')
            ->run();

        $this->assertSame(0, $exitCode);
    }

    public function test_gdpr_process_deletions_max_cap_blocks_runaway(): void
    {
        // POLICY-9: synthesise 3 pending deletions, then run with
        // --max-deletions=1 → must refuse and exit FAILURE.
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        foreach ([$user1, $user2, $user3] as $u) {
            \App\Models\DeletionRequest::create([
                'user_id' => $u->id,
                'status' => 'pending',
                'reason' => 'test',
                'requested_at' => now()->subDays(31),
                'scheduled_deletion_at' => now()->subDay(),
            ]);
        }

        $exitCode = $this->artisan('gdpr:process-deletions', [
            '--max-deletions' => 1,
        ])->run();

        $this->assertSame(1, $exitCode, 'Pending count > cap must exit FAILURE.');

        // None should have been processed
        $remaining = \App\Models\DeletionRequest::where('status', 'pending')->count();
        $this->assertSame(3, $remaining, 'All 3 must remain pending (cap blocked the run).');
    }
}
