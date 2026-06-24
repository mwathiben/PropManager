<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract for the canonical scope-owner resolver. The expression
 * `isScopeOwner() ? id : landlord_id` was duplicated across 100+
 * controller/service methods; this collapses it to one method with an
 * explicit null contract:
 *
 *  - effectiveScopeId(): int        -> fail-closed; resolves to 0 (an id that
 *    belongs to no landlord) when no scope is resolvable (super-admin /
 *    unattached account) so a scoped query matches nothing and a written
 *    landlord_id can never silently inherit another tenant's scope.
 *  - effectiveScopeIdOrNull(): ?int -> the same resolution, but returns null
 *    for the legitimately scope-less actor (super-admin, cross-cutting
 *    middleware, GDPR/audit self-service) that must tell no-scope from 0.
 */
class UserEffectiveScopeIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_resolves_to_its_own_id(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->assertSame((int) $landlord->id, $landlord->effectiveScopeId());
        $this->assertSame((int) $landlord->id, $landlord->effectiveScopeIdOrNull());
    }

    public function test_manager_resolves_to_its_own_id(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->assertSame((int) $manager->id, $manager->effectiveScopeId());
        $this->assertSame((int) $manager->id, $manager->effectiveScopeIdOrNull());
    }

    public function test_caretaker_resolves_to_its_landlord_id(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);

        $this->assertSame((int) $landlord->id, $caretaker->effectiveScopeId());
        $this->assertSame((int) $landlord->id, $caretaker->effectiveScopeIdOrNull());
    }

    public function test_tenant_resolves_to_its_landlord_id(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $this->assertSame((int) $landlord->id, $tenant->effectiveScopeId());
    }

    public function test_caretaker_of_a_manager_resolves_to_the_manager_scope(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $manager->id]);

        $this->assertSame((int) $manager->id, $caretaker->effectiveScopeId());
    }

    public function test_unattached_account_fails_closed_to_zero(): void
    {
        $orphan = User::factory()->make(['role' => 'caretaker', 'landlord_id' => null]);

        $this->assertSame(0, $orphan->effectiveScopeId());
        $this->assertNull($orphan->effectiveScopeIdOrNull());
    }

    public function test_super_admin_has_no_single_scope(): void
    {
        $superAdmin = User::factory()->make(['role' => 'super_admin', 'landlord_id' => null]);

        $this->assertSame(0, $superAdmin->effectiveScopeId());
        $this->assertNull($superAdmin->effectiveScopeIdOrNull());
    }
}
