<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The manager role rests on one invariant: a manager is its OWN scope owner, so
 * `landlord_id == id`. The whole codebase's `isScopeOwner() ? id : landlord_id`
 * scope resolution is correct for managers ONLY while this holds. It is enforced
 * by a saved() hook on the User model — which a raw query-builder mass-update
 * would bypass. These tests prove the enforcement works on every Eloquent path
 * and that caretaker scope resolves to the managing account, so the invariant
 * can't silently drift (which would corrupt every manager's data scope, not just
 * 403 them).
 */
class ManagerScopeInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_factory_created_manager_owns_its_own_scope(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->assertSame((int) $manager->id, (int) $manager->landlord_id, 'A manager must be its own scope owner (landlord_id == id).');
    }

    public function test_promoting_a_user_to_manager_repoints_landlord_id_to_self(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        // role is guarded (mass-assignment-protected), so a realistic promotion
        // sets the attribute directly; the saved() hook then enforces the
        // invariant. (A raw query-builder update would bypass the hook — see the
        // class docblock; the conversion migration sets landlord_id explicitly.)
        $user->role = 'manager';
        $user->save();

        $this->assertSame((int) $user->id, (int) $user->fresh()->landlord_id);
    }

    public function test_a_manager_is_a_scope_owner_and_resolves_to_its_own_id(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->assertTrue($manager->isScopeOwner());
        $resolved = $manager->isScopeOwner() ? $manager->id : $manager->landlord_id;
        $this->assertSame((int) $manager->id, (int) $resolved);
    }

    public function test_a_caretaker_of_a_manager_resolves_to_the_manager_scope(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $manager->id]);

        $this->assertFalse($caretaker->isScopeOwner());
        $resolved = $caretaker->isScopeOwner() ? $caretaker->id : $caretaker->landlord_id;
        $this->assertSame((int) $manager->id, (int) $resolved, 'A caretaker must resolve to its managing account (the manager).');
    }
}
