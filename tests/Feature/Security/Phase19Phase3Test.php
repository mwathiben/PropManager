<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Policies\ImportPolicy;
use App\Policies\TenantPaymentVerificationPolicy;
use App\Policies\TenantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-19 Phase 3 coverage (MEDIUM severity):
 *   POLICY-2: TenantPaymentVerificationPolicy explicit create/update/delete
 *   POLICY-3: TenantPolicy explicit viewAny/create/update/delete
 *   POLICY-4: ImportPolicy explicit update
 *   INDEX-2/3/4/5/7/8: schema-introspection assertions on the new
 *     indexes added by phase19_index_migrations
 */
class Phase19Phase3Test extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_tenant_payment_verification_policy_has_explicit_crud_methods(): void
    {
        $reflection = new ReflectionClass(TenantPaymentVerificationPolicy::class);

        foreach (['create', 'update', 'delete'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "TenantPaymentVerificationPolicy must declare {$method}() (Phase-19 POLICY-2).",
            );
        }
    }

    public function test_tenant_payment_verification_policy_methods_return_false_for_writes(): void
    {
        $policy = new TenantPaymentVerificationPolicy;
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $verification = \App\Models\TenantPaymentVerification::create([
            'lease_id' => $tenantSetup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'status' => 'pending_payment',
            'deposit_required' => '5000.00',
            'first_rent_required' => '5000.00',
            'other_charges' => '0.00',
            'total_required' => '10000.00',
            'amount_paid' => '0.00',
        ]);

        $this->assertFalse($policy->create($setup['landlord']), 'create must return false (submission via dedicated flow).');
        $this->assertFalse($policy->update($setup['landlord'], $verification), 'update must return false (immutable post-submission).');
        $this->assertFalse($policy->delete($setup['landlord'], $verification), 'delete must return false (compliance retention).');
    }

    public function test_tenant_policy_declares_explicit_viewany_and_write_methods(): void
    {
        $reflection = new ReflectionClass(TenantPolicy::class);

        foreach (['viewAny', 'create', 'update', 'delete'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "TenantPolicy must declare {$method}() (Phase-19 POLICY-3).",
            );
        }
    }

    public function test_tenant_policy_viewany_allows_landlord_and_caretaker(): void
    {
        $policy = new TenantPolicy;
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $this->assertTrue($policy->viewAny($landlord));
        $this->assertTrue($policy->viewAny($caretaker));
        $this->assertFalse($policy->viewAny($tenant));
    }

    public function test_tenant_policy_create_and_delete_always_return_false(): void
    {
        $policy = new TenantPolicy;
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $this->assertFalse($policy->create($landlord), 'create must return false (invitation-only flow).');
        $this->assertFalse($policy->delete($landlord, $tenant), 'delete must return false (GDPR right-to-erasure flow).');
    }

    public function test_tenant_policy_update_mirrors_manages_tenant(): void
    {
        $policy = new TenantPolicy;
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $tenantOfA = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlordA->id]);

        $this->assertTrue($policy->update($landlordA, $tenantOfA));
        $this->assertFalse($policy->update($landlordB, $tenantOfA), 'Foreign landlord must NOT update.');
    }

    public function test_import_policy_declares_explicit_update(): void
    {
        $reflection = new ReflectionClass(ImportPolicy::class);

        $this->assertTrue(
            $reflection->hasMethod('update'),
            'ImportPolicy must declare update() (Phase-19 POLICY-4).',
        );

        $policy = new ImportPolicy;
        $landlord = User::factory()->create(['role' => 'landlord']);

        $import = \App\Models\Import::create([
            'landlord_id' => $landlord->id,
            'imported_by' => $landlord->id,
            'type' => 'tenants',
            'status' => \App\Enums\ImportStatus::Completed,
            'file_path' => 'imports/test.csv',
            'file_name' => 'test.csv',
            'total_rows' => 10,
            'successful_rows' => 10,
            'failed_rows' => 0,
        ]);

        $this->assertFalse(
            $policy->update($landlord, $import),
            'ImportPolicy::update must return false (imports immutable post-upload).',
        );
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function phase19IndexProvider(): array
    {
        return [
            ['late_fees', 'late_fees_landlord_active_date_idx'],
            ['late_fees', 'late_fees_active_invoice_fee_idx'],
            ['invoice_items', 'invoice_items_invoice_id_idx'],
            ['buildings', 'buildings_landlord_id_idx'],
            ['units', 'units_landlord_id_idx'],
            ['properties', 'properties_landlord_created_idx'],
            ['wallet_transactions', 'wallet_transactions_landlord_created_idx'],
            ['invoices', 'invoices_landlord_status_due_covering_idx'],
            ['expenses', 'expenses_category_id_idx'],
            ['expenses', 'expenses_vendor_id_idx'],
            ['expenses', 'expenses_property_date_idx'],
            ['expenses', 'expenses_building_date_idx'],
            ['expenses', 'expenses_unit_date_idx'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phase19IndexProvider')]
    public function test_phase19_index_exists(string $table, string $indexName): void
    {
        $this->assertTrue(
            Schema::hasIndex($table, $indexName),
            "Phase-19: {$table}.{$indexName} must exist post-migration.",
        );
    }

    public function test_phase19_dropped_phase15_perf2_index_is_absent(): void
    {
        // INDEX-5 supersedes Phase-15 PERF-2 (landlord_id, status,
        // due_date) with the 5-column covering version. The bare
        // 3-column prefix must be dropped to avoid index redundancy.
        $this->assertFalse(
            Schema::hasIndex('invoices', 'invoices_landlord_status_due_idx'),
            'Phase-19 INDEX-5: the Phase-15 PERF-2 3-column prefix index must be dropped (covering version supersedes).',
        );
    }
}
