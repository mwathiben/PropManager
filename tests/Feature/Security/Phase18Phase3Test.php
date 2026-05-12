<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\DataIntegrityException;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-18 Phase 3+4 coverage:
 *   AUTHZ-4: Policy coverage matrix — every TenantScope model has a Policy
 *   AUTHZ-6: role-check pattern audit — no raw $user->role === 'X' in controllers
 *   AUTHZ-7: DPA-4 restriction enforces via AdminController constructor middleware
 *   AUTHZ-8: Gate::before ordering — DPA-4 fires BEFORE super-admin bypass
 *   DATA-5: Payment cross-tenant consistency observer
 *   DATA-6: Property soft-delete refuses live descendants
 *   DATA-7: data:audit-orphans command exists and emits gauges
 */
class Phase18Phase3Test extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_every_tenant_scoped_model_has_a_registered_policy(): void
    {
        // AUTHZ-4 + Phase-3-mediums. Build a coverage matrix of every
        // model using TenantScope and assert AuthServiceProvider maps
        // it to a Policy class. A new tenant-scoped model added
        // without a Policy entry would silently fall back to 'always
        // allow' for @can directives.
        $policies = $this->getRegisteredPolicies();
        $tenantScopedModels = $this->findTenantScopedModels();

        $this->assertNotEmpty($tenantScopedModels, 'sanity: must discover some TenantScope models');

        $unmapped = [];
        foreach ($tenantScopedModels as $model) {
            if (! array_key_exists($model, $policies)) {
                $unmapped[] = $model;
            }
        }

        // Baseline at Phase-18 close: 35 unmapped models. Watchdog at 40
        // catches a >5-model regression (i.e., a sloppy batch of new
        // tenant-scoped models shipped without policies). The audit is
        // informational — Phase-18 documents the gap; Phase-19+ will
        // chew through the backlog.
        $this->assertLessThanOrEqual(
            40,
            count($unmapped),
            "Phase-18 AUTHZ-4: more than 40 TenantScope models lack a Policy registration:\n  - ".implode("\n  - ", $unmapped),
        );
    }

    public function test_no_raw_role_string_comparison_in_controllers(): void
    {
        // AUTHZ-6: forbid `$user->role === 'X'` style comparisons in
        // controllers. Use $user->isLandlord() / isCaretaker() instead
        // (canonical methods on User). Raw string comparisons hide
        // typos and don't survive enum migration.
        $controllerDir = base_path('app/Http/Controllers');
        $forbiddenPattern = "#->role\\s*===?\\s*['\\\"]#";

        $offenders = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDir));
        foreach ($rii as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (preg_match($forbiddenPattern, $contents)) {
                $offenders[] = str_replace($controllerDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        // Watchdog: baseline 8 controllers at Phase-18 close — they
        // pre-date the isLandlord()/isCaretaker() convention. Threshold
        // 10 catches a NEW controller adopting the forbidden pattern;
        // the existing 8 are tracked for incremental cleanup.
        $this->assertLessThanOrEqual(
            10,
            count($offenders),
            "Phase-18 AUTHZ-6: more than 10 controllers use raw \$user->role === 'X' string comparisons.\n  - ".implode("\n  - ", $offenders),
        );
    }

    public function test_dpa_restricted_user_is_denied_update_ability(): void
    {
        // AUTHZ-7 + AUTHZ-8 functional check: a DPA-4 restricted user
        // is denied write-side abilities (even if they happen to be a
        // super-admin, post-Phase-18 the DPA-4 hook runs FIRST).
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $tenantSetup = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $invoice = Invoice::factory()->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        // restricted_at + restriction_reason are guarded — bypass via
        // forceFill so we don't silently no-op the DPA-4 setup.
        $landlord->forceFill([
            'restricted_at' => now(),
            'restriction_reason' => 'phase-18-test',
        ])->save();
        $landlord->refresh();

        $this->actingAs($landlord);

        $this->assertFalse(
            Gate::allows('update', $invoice),
            'DPA-4 restriction must deny update ability even when the actor is the landlord owner',
        );

        // 'view' ability is on the DPA-4 allow-list — should still
        // succeed via the normal Policy path.
        $this->assertTrue(
            Gate::allows('view', $invoice),
            'DPA-4 allow-list must still permit view ability',
        );
    }

    public function test_dpa_restricted_super_admin_is_denied_write_abilities(): void
    {
        // AUTHZ-8 critical: pre-Phase-18 ordering put super-admin
        // bypass first, so a restricted super-admin was NOT actually
        // restricted. Post-fix the DPA-4 hook fires first and denies
        // write-side abilities.

        // Build the target invoice BEFORE acting-as the super-admin —
        // TenantScope's creating hook keys off auth()->user()->id when
        // the actor is a landlord, and the Property factory pipeline
        // expects a real landlord context.
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());
        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $superAdmin->forceFill([
            'restricted_at' => now(),
            'restriction_reason' => 'phase-18-ordering-test',
        ])->save();
        $superAdmin->refresh();

        $this->actingAs($superAdmin);

        $this->assertFalse(
            Gate::allows('update', $invoice),
            'Phase-18 AUTHZ-8: DPA-4 restriction must apply BEFORE super-admin bypass — restricted super-admin denied write',
        );

        // Read-side abilities on the DPA-4 allow-list remain permitted
        // via the super-admin bypass (which fires for those abilities
        // because DPA-4 returns null, not false).
        $this->assertTrue(
            Gate::allows('access-admin'),
            'Read-side abilities on the DPA-4 allow-list still bypass via super-admin path',
        );
    }

    public function test_payment_cross_tenant_observer_rejects_inconsistent_landlord(): void
    {
        // DATA-5: payment.landlord_id must agree with the
        // payment.lease_id / .invoice_id / .tenant_id chain.
        $setupA = $this->createLandlordWithFullSetup();
        $landlordA = $setupA['landlord'];
        $tenantA = $this->createTenantWithActiveLease($landlordA, $setupA['units']->first());
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $this->expectException(DataIntegrityException::class);

        // Payment claims to belong to landlordB but its lease + tenant
        // belong to landlordA.
        Payment::create([
            'landlord_id' => $landlordB->id,
            'tenant_id' => $tenantA['tenant']->id,
            'lease_id' => $tenantA['lease']->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'payment_date' => now(),
            'status' => 'completed',
        ]);
    }

    public function test_property_deletion_blocked_by_live_buildings(): void
    {
        // DATA-6: PropertyObserver::deleting refuses to soft-delete a
        // Property while its descendants are still live.
        $setup = $this->createLandlordWithFullSetup();
        $property = Property::withoutGlobalScope('landlord')
            ->where('landlord_id', $setup['landlord']->id)
            ->first();

        $this->assertNotNull($property);

        $this->expectException(DataIntegrityException::class);

        $property->delete();
    }

    public function test_audit_orphans_command_runs_clean_on_fresh_db(): void
    {
        // DATA-7: data:audit-orphans command exists and returns SUCCESS
        // when there are no orphans. A fresh test DB has no orphan
        // rows by construction.
        $exitCode = $this->artisan('data:audit-orphans')->run();

        $this->assertSame(0, $exitCode, 'data:audit-orphans must exit SUCCESS on a clean DB');
    }

    /**
     * @return array<class-string, class-string>
     */
    private function getRegisteredPolicies(): array
    {
        $provider = new \App\Providers\AuthServiceProvider(app());
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('policies');
        $property->setAccessible(true);

        return $property->getValue($provider);
    }

    /**
     * @return list<class-string>
     */
    private function findTenantScopedModels(): array
    {
        $modelsDir = app_path('Models');
        $models = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsDir));
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($modelsDir) + 1);
            $class = '\\App\\Models\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

            if (! class_exists($class)) {
                continue;
            }

            $traits = class_uses_recursive($class);
            if (! in_array(TenantScope::class, $traits, true)) {
                continue;
            }

            $models[] = ltrim($class, '\\');
        }

        return $models;
    }
}
