<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use App\Models\MoveOutDeductionCategory;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveOutDeductionCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'name' => 'Block A',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);
    }

    public function test_category_belongs_to_landlord(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forLandlord($this->landlord)
            ->create();

        $this->assertEquals($this->landlord->id, $category->landlord_id);
        $this->assertInstanceOf(User::class, $category->landlord);
        $this->assertEquals($this->landlord->id, $category->landlord->id);
    }

    public function test_category_belongs_to_building(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->create();

        $this->assertEquals($this->building->id, $category->building_id);
        $this->assertInstanceOf(Building::class, $category->building);
    }

    public function test_category_has_many_deductions(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create();
        $moveOut = MoveOut::factory()->create(['landlord_id' => $this->landlord->id]);

        MoveOutDeduction::factory()->count(3)->create([
            'move_out_id' => $moveOut->id,
            'category_id' => $category->id,
        ]);

        $this->assertCount(3, $category->deductions);
    }

    public function test_deduction_belongs_to_category(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create();
        $moveOut = MoveOut::factory()->create(['landlord_id' => $this->landlord->id]);

        $deduction = MoveOutDeduction::factory()->create([
            'move_out_id' => $moveOut->id,
            'category_id' => $category->id,
        ]);

        $this->assertInstanceOf(MoveOutDeductionCategory::class, $deduction->category);
        $this->assertEquals($category->id, $deduction->category->id);
    }

    public function test_global_scope_returns_platform_defaults(): void
    {
        MoveOutDeductionCategory::factory()->platformDefault()->create(['name' => 'Global Category']);
        MoveOutDeductionCategory::factory()->forLandlord($this->landlord)->create(['name' => 'Landlord Category']);

        $globals = MoveOutDeductionCategory::withoutGlobalScope('landlord')
            ->global()
            ->get();

        $this->assertCount(1, $globals);
        $this->assertNull($globals->first()->landlord_id);
        $this->assertEquals('Global Category', $globals->first()->name);
    }

    public function test_active_scope_excludes_inactive_categories(): void
    {
        MoveOutDeductionCategory::factory()->platformDefault()->active()->create(['name' => 'Active']);
        MoveOutDeductionCategory::factory()->platformDefault()->inactive()->create(['name' => 'Inactive']);

        $active = MoveOutDeductionCategory::withoutGlobalScope('landlord')
            ->global()
            ->active()
            ->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    public function test_always_apply_scope_returns_auto_apply_categories(): void
    {
        MoveOutDeductionCategory::factory()->platformDefault()->alwaysApply()->create(['name' => 'Auto Apply']);
        MoveOutDeductionCategory::factory()->platformDefault()->optional()->create(['name' => 'Manual']);

        $autoApply = MoveOutDeductionCategory::withoutGlobalScope('landlord')
            ->alwaysApply()
            ->get();

        $this->assertCount(1, $autoApply);
        $this->assertEquals('Auto Apply', $autoApply->first()->name);
    }

    public function test_for_building_scope_includes_landlord_defaults(): void
    {
        MoveOutDeductionCategory::factory()->forLandlord($this->landlord)->create([
            'name' => 'Landlord Default',
        ]);

        MoveOutDeductionCategory::factory()->forBuilding($this->building)->create([
            'name' => 'Building Specific',
        ]);

        $categories = MoveOutDeductionCategory::where('landlord_id', $this->landlord->id)
            ->forBuilding($this->building->id)
            ->get();

        $this->assertCount(2, $categories);
        $this->assertTrue($categories->contains('name', 'Landlord Default'));
        $this->assertTrue($categories->contains('name', 'Building Specific'));
    }

    public function test_ordered_scope_sorts_by_sort_order_and_name(): void
    {
        MoveOutDeductionCategory::factory()->platformDefault()->create([
            'name' => 'Zebra',
            'sort_order' => 2,
        ]);
        MoveOutDeductionCategory::factory()->platformDefault()->create([
            'name' => 'Alpha',
            'sort_order' => 1,
        ]);
        MoveOutDeductionCategory::factory()->platformDefault()->create([
            'name' => 'Beta',
            'sort_order' => 1,
        ]);

        $ordered = MoveOutDeductionCategory::withoutGlobalScope('landlord')
            ->ordered()
            ->get();

        $this->assertEquals('Alpha', $ordered[0]->name);
        $this->assertEquals('Beta', $ordered[1]->name);
        $this->assertEquals('Zebra', $ordered[2]->name);
    }

    public function test_is_global_returns_true_for_platform_defaults(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create();

        $this->assertTrue($category->isGlobal());
    }

    public function test_is_global_returns_false_for_landlord_categories(): void
    {
        $category = MoveOutDeductionCategory::factory()->forLandlord($this->landlord)->create();

        $this->assertFalse($category->isGlobal());
    }

    public function test_is_building_specific_returns_true_when_building_set(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->create();

        $this->assertTrue($category->isBuildingSpecific());
    }

    public function test_is_building_specific_returns_false_for_landlord_defaults(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forLandlord($this->landlord)
            ->create();

        $this->assertFalse($category->isBuildingSpecific());
    }

    public function test_existing_deductions_without_category_remain_valid(): void
    {
        $moveOut = MoveOut::factory()->create(['landlord_id' => $this->landlord->id]);

        $deduction = MoveOutDeduction::factory()->create([
            'move_out_id' => $moveOut->id,
            'category_id' => null,
        ]);

        $this->assertNull($deduction->category_id);
        $this->assertNull($deduction->category);
        $this->assertDatabaseHas('move_out_deductions', [
            'id' => $deduction->id,
            'category_id' => null,
        ]);
    }

    public function test_deduction_category_can_be_deleted_without_deleting_deduction(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create();
        $moveOut = MoveOut::factory()->create(['landlord_id' => $this->landlord->id]);

        $deduction = MoveOutDeduction::factory()->create([
            'move_out_id' => $moveOut->id,
            'category_id' => $category->id,
        ]);

        $category->delete();

        $deduction->refresh();
        $this->assertNull($deduction->category_id);
        $this->assertDatabaseHas('move_out_deductions', ['id' => $deduction->id]);
    }

    public function test_duplicate_category_names_in_same_scope_rejected(): void
    {
        // Note: MySQL unique constraints don't consider NULL=NULL, so we test
        // with building scope where all constraint columns have non-null values
        MoveOutDeductionCategory::factory()->forBuilding($this->building)->create([
            'name' => 'Cleaning Fee',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MoveOutDeductionCategory::factory()->forBuilding($this->building)->create([
            'name' => 'Cleaning Fee',
        ]);
    }

    public function test_same_category_name_allowed_in_different_scopes(): void
    {
        MoveOutDeductionCategory::factory()->platformDefault()->create([
            'name' => 'Cleaning Fee',
        ]);

        $category = MoveOutDeductionCategory::factory()->forLandlord($this->landlord)->create([
            'name' => 'Cleaning Fee',
        ]);

        $this->assertDatabaseCount('move_out_deduction_categories', 2);
        $this->assertEquals('Cleaning Fee', $category->name);
        $this->assertEquals($this->landlord->id, $category->landlord_id);
    }

    public function test_factory_cleaning_fee_state(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->platformDefault()
            ->cleaningFee()
            ->create();

        $this->assertEquals('Cleaning Fee', $category->name);
        $this->assertEquals(3000, $category->default_amount);
        $this->assertTrue($category->always_apply);
    }

    public function test_factory_paint_works_state(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->platformDefault()
            ->paintWorks()
            ->create();

        $this->assertEquals('Paint Works', $category->name);
        $this->assertEquals(5000, $category->default_amount);
    }

    public function test_factory_key_replacement_state(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->platformDefault()
            ->keyReplacement()
            ->create();

        $this->assertEquals('Key Replacement', $category->name);
        $this->assertEquals(500, $category->default_amount);
    }

    public function test_deduction_with_category_scope(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create();
        $moveOut = MoveOut::factory()->create(['landlord_id' => $this->landlord->id]);

        MoveOutDeduction::factory()->create([
            'move_out_id' => $moveOut->id,
            'category_id' => $category->id,
        ]);
        MoveOutDeduction::factory()->create([
            'move_out_id' => $moveOut->id,
            'category_id' => null,
        ]);

        $withCategory = MoveOutDeduction::withCategory()->get();
        $withoutCategory = MoveOutDeduction::withoutCategory()->get();

        $this->assertCount(1, $withCategory);
        $this->assertCount(1, $withoutCategory);
    }

    public function test_category_default_amount_stored_correctly(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create([
            'default_amount' => 12345.67,
        ]);

        $this->assertEquals('12345.67', $category->default_amount);
    }

    public function test_category_casts_booleans_correctly(): void
    {
        $category = MoveOutDeductionCategory::factory()->platformDefault()->create([
            'always_apply' => true,
            'is_active' => false,
        ]);

        $this->assertTrue($category->always_apply);
        $this->assertFalse($category->is_active);
        $this->assertIsBool($category->always_apply);
        $this->assertIsBool($category->is_active);
    }
}
