<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class BuildingControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_landlord_can_view_buildings_index(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('buildings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Buildings/Index')
            ->has('buildingGroups', 1)
        );
    }

    public function test_landlord_can_create_standalone_building(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.storeStandalone'), [
                'name' => 'New Building',
                'building_type' => 'residential_apartment',
                'total_floors' => 3,
                'units_per_floor' => 4,
                'address' => '456 New Street',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('buildings', [
            'name' => 'New Building',
            'building_type' => 'residential_apartment',
            'total_floors' => 3,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_landlord_can_view_building_details(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->get(route('buildings.show', $building));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Buildings/Show')
            ->where('building.id', $building->id)
        );
    }

    public function test_landlord_can_view_building_dashboard(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->get(route('buildings.dashboard', $building));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Buildings/Dashboard')
            ->where('activeBuilding.id', $building->id)
        );
    }

    public function test_landlord_can_update_building_settings(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.update-settings', $building), [
                'name' => 'Renamed Building',
                'building_type' => 'office_block',
                'address' => 'Updated Address',
            ]);

        $response->assertRedirect();

        $building->refresh();
        $this->assertEquals('Renamed Building', $building->name);
        $this->assertEquals('office_block', $building->building_type);
    }

    public function test_landlord_can_configure_water_settings(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.water-settings.update', $building), [
                'water_billing_type' => 'flat_rate',
                'water_flat_rate' => 750,
            ]);

        $response->assertRedirect();

        $building->refresh();
        $this->assertEquals('flat_rate', $building->water_billing_type);
        $this->assertEquals(750, $building->water_flat_rate);
    }

    public function test_landlord_cannot_access_other_landlords_building(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $building = $this->setupData['building'];

        $response = $this->actingAs($otherLandlord)
            ->get(route('buildings.show', $building));

        $response->assertForbidden();
    }

    public function test_building_validation_requires_name(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.storeStandalone'), [
                'building_type' => 'residential_apartment',
                'total_floors' => 2,
                'units_per_floor' => 4,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_building_validation_requires_valid_type(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.storeStandalone'), [
                'name' => 'Test Building',
                'building_type' => 'invalid_type',
                'total_floors' => 2,
                'units_per_floor' => 4,
            ]);

        $response->assertSessionHasErrors('building_type');
    }

    public function test_building_amenities_can_be_updated(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.update-settings', $building), [
                'name' => $building->name,
                'building_type' => $building->building_type,
                'amenities' => [
                    'selected' => ['wifi', 'parking', 'cctv'],
                    'custom' => ['Rooftop Garden'],
                ],
            ]);

        $response->assertRedirect();

        $building->refresh();
        $this->assertContains('wifi', $building->amenities['selected'] ?? []);
        $this->assertContains('Rooftop Garden', $building->amenities['custom'] ?? []);
    }

    public function test_building_coordinates_can_be_updated(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.update-settings', $building), [
                'name' => $building->name,
                'building_type' => $building->building_type,
                'coordinates' => [
                    'lat' => -1.2921,
                    'lng' => 36.8219,
                ],
            ]);

        $response->assertRedirect();

        $building->refresh();
        $this->assertEquals(-1.2921, $building->coordinates['lat']);
        $this->assertEquals(36.8219, $building->coordinates['lng']);
    }

    public function test_landlord_can_bulk_update_unit_rent(): void
    {
        $building = $this->setupData['building'];
        $unitIds = $building->units()->pluck('id')->all();

        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => $unitIds,
                'action' => 'update_rent',
                'value' => '30000',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        foreach ($unitIds as $id) {
            $this->assertDatabaseHas('units', ['id' => $id, 'target_rent' => 30000]);
        }
    }

    public function test_landlord_can_bulk_update_unit_type(): void
    {
        $building = $this->setupData['building'];
        $unitIds = $building->units()->pluck('id')->all();

        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => $unitIds,
                'action' => 'update_type',
                'value' => 'commercial',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unitIds[0], 'unit_type' => 'commercial']);
    }

    public function test_landlord_can_add_unit(): void
    {
        $building = $this->setupData['building'];

        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.add-unit', $building), [
                'floor_number' => 3,
                'unit_number' => 'Z301',
                'target_rent' => 25000,
                'unit_type' => 'residential',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('units', [
            'building_id' => $building->id,
            'unit_number' => 'Z301',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_added_unit_above_total_floors_raises_building_total_floors(): void
    {
        $building = $this->setupData['building'];
        $newFloor = $building->total_floors + 5;

        $response = $this->actingAs($this->landlord)
            ->post(route('buildings.add-unit', $building), [
                'floor_number' => $newFloor,
                'unit_number' => 'Z'.($newFloor * 100 + 1),
                'target_rent' => 25000,
                'unit_type' => 'residential',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $building->refresh();
        $this->assertSame($newFloor, $building->total_floors);
    }
}
