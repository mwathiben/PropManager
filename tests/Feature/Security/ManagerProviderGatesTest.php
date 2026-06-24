<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Resources\TenantResource;
use App\Models\User;
use App\Support\AuthAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * MANAGER-AUTHZ-2: the Provider-gate + API-Resource parity gap.
 *
 * A `manager` is a full scope owner (landlord_id == its own id) and must be
 * authorized wherever a landlord is. The ManagerAuthzGateTest guardrail scanned
 * Requests/Policies/Services/Observers/Broadcasting/Controllers — but NOT
 * app/Providers or app/Http/Resources — so the cross-cutting Gates defined in
 * AuthServiceProvider and the field-gating in TenantResource still excluded
 * managers via isLandlord(). These pin the corrected behaviour.
 *
 * The single deliberate exception is `manage-subscription`: a manager runs
 * properties on the owners' behalf but does NOT own the platform billing
 * relationship, so it stays landlord-only.
 */
class ManagerProviderGatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every Gate in the $manageGates set represents a manager's core job
     * (running properties on owners' behalf) — a manager must pass all of them.
     *
     * @return list<array{string}>
     */
    public static function managementGates(): array
    {
        return [
            'tenants:manage' => ['tenants:manage'],
            'invoices:manage' => ['invoices:manage'],
            'payments:manage' => ['payments:manage'],
            'properties:manage' => ['properties:manage'],
            'buildings:manage' => ['buildings:manage'],
            'units:manage' => ['units:manage'],
            'documents:manage' => ['documents:manage'],
            'settings:manage' => ['settings:manage'],
            'team:manage' => ['team:manage'],
            'templates:manage' => ['templates:manage'],
            'finances:manage' => ['finances:manage'],
            'imports:manage' => ['imports:manage'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('managementGates')]
    public function test_manager_passes_management_gate(string $ability): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue(
            Gate::forUser($manager)->allows($ability),
            "Manager (scope owner) must pass the '{$ability}' Gate.",
        );
    }

    public function test_manager_passes_view_audit_logs_gate(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue(
            Gate::forUser($manager)->allows('view-audit-logs'),
            'Manager must see its own scope audit logs (view-audit-logs Gate).',
        );
    }

    public function test_manager_passes_view_api_docs_gate(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue(
            Gate::forUser($manager)->allows('viewApiDocs'),
            'Manager must access API docs for its own scope (viewApiDocs Gate).',
        );
    }

    public function test_manager_is_denied_manage_subscription_gate(): void
    {
        // Phase-19 POLICY-5: manage-subscription is landlord-only BY DESIGN. A
        // manager does not own the platform subscription/billing relationship.
        $manager = User::factory()->manager()->create();

        $this->assertFalse(
            Gate::forUser($manager)->allows('manage-subscription'),
            'Manager must NOT manage the subscription — the account owner (landlord) does.',
        );
    }

    public function test_auth_abilities_map_grants_manager_management_gates(): void
    {
        // AuthAbilities::for() delegates to the Gate registry, so the corrected
        // Gates must flow through to the Vue frontend abilities map for managers.
        $manager = User::factory()->manager()->create();
        $abilities = AuthAbilities::for($manager);

        foreach (array_keys(self::managementGates()) as $ability) {
            $this->assertTrue(
                $abilities[$ability],
                "AuthAbilities map must expose '{$ability}' => true to a manager.",
            );
        }

        $this->assertTrue($abilities['view-audit-logs'], 'Manager abilities map must grant view-audit-logs.');
        $this->assertFalse($abilities['manage-subscription'], 'Manager abilities map must deny manage-subscription.');
    }

    public function test_tenant_resource_exposes_sensitive_fields_to_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $manager->id,
            'national_id' => 'A1234567',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+254700000000',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $manager);

        $data = (new TenantResource($tenant))->resolve($request);

        $this->assertArrayHasKey('national_id', $data, 'Manager must see tenant national_id in its scope.');
        $this->assertSame('A1234567', $data['national_id']);
        $this->assertArrayHasKey('emergency_contact_name', $data);
        $this->assertArrayHasKey('emergency_contact_phone', $data);
    }

    public function test_tenant_resource_hides_sensitive_fields_from_unrelated_tenant(): void
    {
        // Negative control: the field gate is real, not always-open.
        $manager = User::factory()->manager()->create();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $manager->id,
            'national_id' => 'A1234567',
        ]);
        $otherTenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $manager->id,
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $otherTenant);

        $data = (new TenantResource($tenant))->resolve($request);

        $this->assertArrayNotHasKey('national_id', $data, 'An unrelated tenant must NOT see another tenant national_id.');
    }
}
