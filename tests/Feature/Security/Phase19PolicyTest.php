<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Policies\BuildingPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KycRequirementPolicy;
use App\Policies\LeasePolicy;
use App\Policies\PropertyPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-19 Phase 1 coverage (HIGH severity):
 *   POLICY-1: forceDelete + restore methods on 8 SoftDelete-using Policies
 *   POLICY-5: SubscriptionController gated via Gate::authorize('manage-subscription')
 *             — restricted landlord blocked at DPA-4 layer
 *   POLICY-6: AuditLogController scopes via canAccessAllAuditLogs() —
 *             a DPA-4 restricted super-admin no longer sees full-system logs
 */
class Phase19PolicyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * @return array<int, array{0: class-string, 1: string}>
     */
    public static function softDeletePolicyClasses(): array
    {
        return [
            [BuildingPolicy::class, Building::class],
            [DocumentPolicy::class, Document::class],
            [InvoicePolicy::class, Invoice::class],
            [KycRequirementPolicy::class, KycRequirement::class],
            [LeasePolicy::class, Lease::class],
            [PropertyPolicy::class, Property::class],
            [UnitPolicy::class, Unit::class],
            [TenantPolicy::class, User::class],
        ];
    }

    #[DataProvider('softDeletePolicyClasses')]
    public function test_each_softdelete_policy_declares_force_delete_and_restore(string $policyClass, string $modelClass): void
    {
        $reflection = new ReflectionClass($policyClass);

        $this->assertTrue(
            $reflection->hasMethod('forceDelete'),
            "{$policyClass} must declare forceDelete() (Phase-19 POLICY-1)",
        );

        $this->assertTrue(
            $reflection->hasMethod('restore'),
            "{$policyClass} must declare restore() (Phase-19 POLICY-1)",
        );

        $this->assertTrue(
            $reflection->getMethod('forceDelete')->isPublic(),
            "{$policyClass}::forceDelete() must be public",
        );

        $this->assertTrue(
            $reflection->getMethod('restore')->isPublic(),
            "{$policyClass}::restore() must be public",
        );
    }

    public function test_force_delete_denies_non_super_admins_on_building_policy(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $building = $setup['building'];

        $this->assertFalse(
            Gate::forUser($landlord)->allows('forceDelete', $building),
            'Landlord owner must NOT be allowed to forceDelete (super-admin only).',
        );

        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $this->assertFalse(
            Gate::forUser($otherLandlord)->allows('forceDelete', $building),
            'Foreign landlord must NOT be allowed to forceDelete.',
        );
    }

    public function test_force_delete_allows_super_admin_via_before_hook(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->assertTrue(
            Gate::forUser($superAdmin)->allows('forceDelete', $setup['building']),
            'Super-admin must be allowed via Policy::before() bypass.',
        );
    }

    public function test_restore_mirrors_delete_ownership_for_building_policy(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $building = $setup['building'];

        $this->assertTrue(
            Gate::forUser($landlord)->allows('restore', $building),
            'Landlord owner must be allowed to restore their building (mirrors delete).',
        );

        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $this->assertFalse(
            Gate::forUser($otherLandlord)->allows('restore', $building),
            'Foreign landlord must NOT be allowed to restore.',
        );
    }

    public function test_restricted_super_admin_cannot_force_delete_or_restore(): void
    {
        // POLICY-1 + Phase-13 DPA-4 integration: forceDelete/restore are
        // write-side abilities NOT on the DPA-4 allow-list, so a restricted
        // super-admin is denied at the Gate::before layer before the
        // Policy::before super-admin bypass runs.
        $setup = $this->createLandlordWithFullSetup();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'restricted_at' => now(),
        ]);

        $this->assertFalse(
            Gate::forUser($superAdmin)->allows('forceDelete', $setup['building']),
            'DPA-restricted super-admin must NOT forceDelete (write-side ability).',
        );

        $this->assertFalse(
            Gate::forUser($superAdmin)->allows('restore', $setup['building']),
            'DPA-restricted super-admin must NOT restore (write-side ability).',
        );
    }

    public function test_manage_subscription_gate_is_defined(): void
    {
        // POLICY-5: Phase-18 deleted the dead Gate; Phase-19 resurrects
        // it with a real call site in SubscriptionController.
        $this->assertTrue(
            Gate::has('manage-subscription'),
            'Gate::define(\'manage-subscription\') must exist (Phase-19 POLICY-5).',
        );

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertTrue(
            Gate::forUser($landlord)->allows('manage-subscription'),
            'Landlord must be allowed to manage-subscription.',
        );

        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->assertFalse(
            Gate::forUser($tenant)->allows('manage-subscription'),
            'Tenant must NOT be allowed to manage-subscription.',
        );
    }

    public function test_restricted_landlord_blocked_on_subscription_subscribe(): void
    {
        // POLICY-5: pre-Phase-19 a restricted landlord could POST to
        // /subscription/subscribe because the inline role check bypassed
        // the Gate layer. Post-fix the Gate::authorize hits DPA-4 first.
        $restrictedLandlord = User::factory()->create([
            'role' => 'landlord',
            'restricted_at' => now(),
        ]);

        $this->assertFalse(
            Gate::forUser($restrictedLandlord)->allows('manage-subscription'),
            'DPA-restricted landlord must NOT pass manage-subscription Gate.',
        );
    }

    public function test_can_access_all_audit_logs_predicate(): void
    {
        // POLICY-6: User::canAccessAllAuditLogs() returns true only when
        // super-admin AND not restricted. Restricted super-admin returns
        // false → falls back to scoped query path.
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue(
            $superAdmin->canAccessAllAuditLogs(),
            'Unrestricted super-admin can access all audit logs.',
        );

        $restrictedSuperAdmin = User::factory()->create([
            'role' => 'super_admin',
            'restricted_at' => now(),
        ]);
        $this->assertFalse(
            $restrictedSuperAdmin->canAccessAllAuditLogs(),
            'DPA-restricted super-admin must NOT access all audit logs (POLICY-6).',
        );

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertFalse(
            $landlord->canAccessAllAuditLogs(),
            'Landlord must NOT access all audit logs.',
        );
    }

    public function test_audit_log_index_scopes_for_restricted_super_admin(): void
    {
        // POLICY-6 functional: pre-fix the controller scoped via
        // isSuperAdmin() and returned ALL rows for a restricted super-admin.
        // Post-fix it scopes via canAccessAllAuditLogs() which returns
        // false → query goes to landlord_id branch.
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        AuditLog::create([
            'event_type' => 'created',
            'description' => 'A test event',
            'auditable_type' => 'App\\Models\\Invoice',
            'auditable_id' => 1,
            'user_id' => $landlordA->id,
            'landlord_id' => $landlordA->id,
        ]);

        AuditLog::create([
            'event_type' => 'created',
            'description' => 'B test event',
            'auditable_type' => 'App\\Models\\Invoice',
            'auditable_id' => 2,
            'user_id' => $landlordB->id,
            'landlord_id' => $landlordB->id,
        ]);

        $restrictedSuperAdmin = User::factory()->create([
            'role' => 'super_admin',
            'restricted_at' => now(),
        ]);

        $response = $this->actingAs($restrictedSuperAdmin)->get('/audit-logs');

        $response->assertOk();

        // Restricted super-admin: canAccessAllAuditLogs() returns false →
        // the controller takes the non-super-admin scoping path. Restricted
        // super-admin has no landlord_id (they are super_admin) so neither
        // branch matches → empty result set, which IS the correct restricted
        // behaviour (no full-system visibility while restricted).
        $logs = $response->viewData('page')['props']['logs']['data'];
        $this->assertEmpty(
            $logs,
            'Restricted super-admin must see ZERO logs (no full-system visibility, no landlord scope).',
        );
    }

    public function test_unrestricted_super_admin_sees_all_audit_logs(): void
    {
        // POLICY-6 negative test: confirm we did NOT break the existing
        // unrestricted super-admin path.
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        AuditLog::create([
            'event_type' => 'created',
            'description' => 'A test event',
            'auditable_type' => 'App\\Models\\Invoice',
            'auditable_id' => 1,
            'user_id' => $landlordA->id,
            'landlord_id' => $landlordA->id,
        ]);

        AuditLog::create([
            'event_type' => 'created',
            'description' => 'B test event',
            'auditable_type' => 'App\\Models\\Invoice',
            'auditable_id' => 2,
            'user_id' => $landlordB->id,
            'landlord_id' => $landlordB->id,
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get('/audit-logs');

        $response->assertOk();

        $logs = $response->viewData('page')['props']['logs']['data'];
        $this->assertGreaterThanOrEqual(
            2,
            count($logs),
            'Unrestricted super-admin must see all audit logs (existing Phase-18 behaviour).',
        );
    }
}
