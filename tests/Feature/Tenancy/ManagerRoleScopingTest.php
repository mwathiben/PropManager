<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Building;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The `manager` role (firm/individual managing on owners' behalf) is a
 * first-class scope owner — it behaves identically to a self-managing
 * landlord for tenancy isolation. These pin that contract.
 */
class ManagerRoleScopingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_manager_is_its_own_scope_owner(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue($manager->isManager());
        $this->assertTrue($manager->isScopeOwner());
        $this->assertFalse($manager->isLandlord());
        // The invariant: a manager keeps landlord_id == its own id.
        $this->assertSame((int) $manager->id, (int) $manager->landlord_id);
    }

    public function test_landlord_is_a_scope_owner_but_a_caretaker_is_not(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $caretaker = $this->createCaretakerForLandlord($landlord);

        $this->assertTrue($landlord->isScopeOwner());
        $this->assertFalse($caretaker->isScopeOwner());
    }

    public function test_manager_scopes_to_and_creates_under_its_own_id(): void
    {
        // Another landlord's data exists and must stay invisible to the manager.
        ['landlord' => $otherLandlord] = $this->createLandlordWithFullSetup();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);
        Model::clearBootedModels();

        // Created with no landlord_id — the creating() hook must stamp the manager's id.
        $property = Property::create([
            'name' => 'Manager Property',
            'address' => '1 Firm Street',
            'type' => 'apartment',
        ]);
        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Managed Block',
            'total_floors' => 1,
            'units_per_floor' => 2,
            'building_type' => 'residential_apartment',
        ]);

        $this->assertSame((int) $manager->id, (int) $building->landlord_id);
        $this->assertSame((int) $manager->id, (int) $property->landlord_id);

        // Read scope: the manager sees only its own buildings, never the other landlord's.
        $this->assertSame([$manager->id], Building::query()->pluck('landlord_id')->unique()->values()->all());
        $this->assertNotContains($otherLandlord->id, Building::query()->pluck('landlord_id')->all());
    }
}
