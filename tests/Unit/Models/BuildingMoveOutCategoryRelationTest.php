<?php

namespace Tests\Unit\Models;

use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingMoveOutCategoryRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_has_move_out_deduction_categories_relationship(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
        ]);

        MoveOutDeductionCategory::factory()
            ->forBuilding($building)
            ->create(['name' => 'Category 1']);

        MoveOutDeductionCategory::factory()
            ->forBuilding($building)
            ->create(['name' => 'Category 2']);

        $this->assertCount(2, $building->moveOutDeductionCategories);
        $this->assertInstanceOf(MoveOutDeductionCategory::class, $building->moveOutDeductionCategories->first());
    }

    public function test_relationship_only_returns_categories_for_this_building(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $building1 = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
        ]);

        $building2 = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
        ]);

        MoveOutDeductionCategory::factory()
            ->forBuilding($building1)
            ->create(['name' => 'Building 1 Category']);

        MoveOutDeductionCategory::factory()
            ->forBuilding($building2)
            ->create(['name' => 'Building 2 Category']);

        MoveOutDeductionCategory::factory()
            ->forLandlord($landlord)
            ->create(['name' => 'Landlord Global']);

        $this->assertCount(1, $building1->moveOutDeductionCategories);
        $this->assertEquals('Building 1 Category', $building1->moveOutDeductionCategories->first()->name);
    }
}
