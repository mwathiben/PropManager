<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A manager (scope-owner) must have the same OPERATIONAL access as a
 * self-managing landlord: admitted to core operational pages, and 403'd
 * on resources that belong to a different scope-owner.
 */
class ManagerAccessTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $manager;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();

        $property = Property::create([
            'name' => 'Manager Test Property',
            'address' => '10 Manager Road',
            'type' => 'apartment',
            'landlord_id' => $this->manager->id,
        ]);

        $this->building = Building::create([
            'property_id' => $property->id,
            'name' => 'Manager Block A',
            'total_floors' => 1,
            'units_per_floor' => 2,
            'landlord_id' => $this->manager->id,
            'building_type' => 'residential_apartment',
        ]);

        Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'MA101',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 20000,
            'landlord_id' => $this->manager->id,
        ]);
    }

    public function test_manager_can_access_dashboard(): void
    {
        $this->actingAs($this->manager)
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_manager_can_access_buildings_index(): void
    {
        $this->actingAs($this->manager)
            ->get('/buildings')
            ->assertStatus(200);
    }

    public function test_manager_can_access_tenants_index(): void
    {
        $this->actingAs($this->manager)
            ->get('/tenants')
            ->assertStatus(200);
    }

    public function test_manager_can_access_tenants_hub(): void
    {
        $this->actingAs($this->manager)
            ->get('/tenants-hub')
            ->assertStatus(200);
    }

    public function test_manager_can_access_finances_index(): void
    {
        $this->actingAs($this->manager)
            ->get('/finances')
            ->assertStatus(200);
    }

    public function test_manager_can_access_payments_hub_overview(): void
    {
        $this->actingAs($this->manager)
            ->get('/payments-hub/overview')
            ->assertStatus(200);
    }

    public function test_manager_own_building_show_passes_ownership_gate(): void
    {
        $this->actingAs($this->manager)
            ->get('/buildings/'.$this->building->id)
            ->assertStatus(200);
    }

    public function test_manager_foreign_building_show_returns_403(): void
    {
        ['landlord' => $otherLandlord, 'building' => $otherBuilding] = $this->createLandlordWithFullSetup();

        $this->actingAs($this->manager)
            ->get('/buildings/'.$otherBuilding->id)
            ->assertStatus(403);
    }
}
