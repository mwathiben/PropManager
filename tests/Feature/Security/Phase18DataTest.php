<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\DataIntegrityException;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-18 Phase 2 coverage:
 *   DATA-1: payments.invoice_id is ON DELETE RESTRICT after the migration
 *   DATA-2: wallets:audit-balances command detects + reports drift
 *   DATA-3: UnitObserver::deleting refuses to delete a Unit with active leases
 */
class Phase18DataTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_payments_invoice_fk_is_restrict_on_delete(): void
    {
        // DATA-1: pre-Phase-18 the FK was CASCADE; force-deleting an
        // Invoice would nuke its Payment audit trail. Post-fix the
        // FK is RESTRICT — the force-delete fails at the DB layer.
        $foreignKeys = Schema::getConnection()
            ->getSchemaBuilder()
            ->getForeignKeys('payments');

        $invoiceFk = collect($foreignKeys)->first(
            fn ($fk) => in_array('invoice_id', $fk['columns'] ?? [], true),
        );

        $this->assertNotNull($invoiceFk, 'payments.invoice_id FK must exist');
        $this->assertSame(
            'restrict',
            strtolower((string) ($invoiceFk['on_delete'] ?? '')),
            'payments.invoice_id must be ON DELETE RESTRICT (Phase-18 DATA-1)',
        );
    }

    public function test_force_deleting_invoice_with_payments_raises_fk_constraint(): void
    {
        // DATA-1 functional: a force-delete on an Invoice with attached
        // Payments must fail at the DB layer (not silently cascade).
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        Payment::create([
            'landlord_id' => $setup['landlord']->id,
            'tenant_id' => $tenantSetup['tenant']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'invoice_id' => $invoice->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'payment_date' => now(),
            'status' => 'completed',
        ]);

        $this->expectException(QueryException::class);

        $invoice->forceDelete();
    }

    public function test_audit_wallet_balances_detects_drift(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        // Recorded wallet_balance says 500; we only insert a single 300
        // credit transaction → 200 KES drift.
        Lease::whereKey($tenantSetup['lease']->id)->update(['wallet_balance' => '500.00']);

        DB::table('wallet_transactions')->insert([
            'lease_id' => $tenantSetup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'type' => 'credit',
            'amount' => '300.00',
            'balance_after' => '500.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = $this->artisan('wallets:audit-balances')->run();

        $this->assertSame(1, $exitCode, 'wallets:audit-balances must exit FAILURE on drift');
    }

    public function test_audit_wallet_balances_passes_when_balanced(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        Lease::whereKey($tenantSetup['lease']->id)->update(['wallet_balance' => '300.00']);

        DB::table('wallet_transactions')->insert([
            'lease_id' => $tenantSetup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'type' => 'credit',
            'amount' => '300.00',
            'balance_after' => '300.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = $this->artisan('wallets:audit-balances')->run();

        $this->assertSame(0, $exitCode, 'wallets:audit-balances must exit SUCCESS when balanced');
    }

    public function test_unit_deletion_blocked_by_active_lease(): void
    {
        // DATA-3: pre-Phase-18 a Unit could be soft-deleted while its
        // active Lease still pointed at it. The Lease then surfaced as
        // 'missing' on dashboard joins (Unit's SoftDeletes global scope
        // hid it). UnitObserver::deleting now throws.
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $unit = $tenantSetup['lease']->unit;
        $this->assertNotNull($unit);
        $this->assertTrue($tenantSetup['lease']->is_active);

        $this->expectException(DataIntegrityException::class);

        $unit->delete();
    }

    public function test_unit_deletion_allowed_when_no_active_lease(): void
    {
        // Negative case: a Unit with no active leases deletes normally.
        $setup = $this->createLandlordWithFullSetup();

        $unit = $setup['units']->first();
        $this->assertNotNull($unit);

        $unit->delete();

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }
}
