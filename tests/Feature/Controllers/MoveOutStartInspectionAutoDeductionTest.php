<?php

namespace Tests\Feature\Controllers;

use App\Models\Building;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use App\Models\MoveOutDeductionCategory;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveOutStartInspectionAutoDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Building $building;

    private Unit $unit;

    private Lease $lease;

    private MoveOut $moveOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->building = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->unit = Unit::factory()->create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::factory()->create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->moveOut = MoveOut::factory()
            ->forLease($this->lease)
            ->noticeGiven()
            ->create();
    }

    public function test_start_inspection_auto_applies_always_apply_deductions(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create([
                'name' => 'Cleaning Fee',
                'default_amount' => 3000,
            ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('move_out_deductions', [
            'move_out_id' => $this->moveOut->id,
            'category_id' => $category->id,
            'description' => 'Cleaning Fee',
            'amount' => 3000,
            'auto_applied' => true,
        ]);
    }

    public function test_auto_applied_flag_is_true_for_auto_created_deductions(): void
    {
        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create([
                'name' => 'Paint Works',
                'default_amount' => 5000,
            ]);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deduction = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->first();

        $this->assertTrue($deduction->auto_applied);
    }

    public function test_only_active_categories_are_auto_applied(): void
    {
        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->active()
            ->create(['name' => 'Active Category']);

        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->inactive()
            ->create(['name' => 'Inactive Category']);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(1, $deductions);
        $this->assertEquals('Active Category', $deductions->first()->description);
    }

    public function test_building_specific_and_global_categories_both_apply(): void
    {
        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create(['name' => 'Building Specific', 'default_amount' => 1000]);

        MoveOutDeductionCategory::factory()
            ->forLandlord($this->landlord)
            ->alwaysApply()
            ->create(['name' => 'Landlord Global', 'default_amount' => 2000]);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(2, $deductions);
        $this->assertTrue($deductions->contains('description', 'Building Specific'));
        $this->assertTrue($deductions->contains('description', 'Landlord Global'));
    }

    public function test_categories_from_other_buildings_are_not_auto_applied(): void
    {
        $otherProperty = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $otherBuilding = Building::factory()->create([
            'property_id' => $otherProperty->id,
            'landlord_id' => $this->landlord->id,
        ]);

        MoveOutDeductionCategory::factory()
            ->forBuilding($otherBuilding)
            ->alwaysApply()
            ->create(['name' => 'Other Building Category']);

        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create(['name' => 'This Building Category']);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(1, $deductions);
        $this->assertEquals('This Building Category', $deductions->first()->description);
    }

    public function test_categories_without_always_apply_are_not_auto_applied(): void
    {
        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create(['name' => 'Always Apply']);

        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->optional()
            ->create(['name' => 'Optional Category']);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(1, $deductions);
        $this->assertEquals('Always Apply', $deductions->first()->description);
    }

    public function test_refund_is_recalculated_after_auto_applying_deductions(): void
    {
        $depositHeld = 50000;
        $this->moveOut->update(['deposit_held' => $depositHeld, 'refund_amount' => $depositHeld]);

        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create(['name' => 'Cleaning', 'default_amount' => 3000]);

        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->alwaysApply()
            ->create(['name' => 'Paint', 'default_amount' => 5000]);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $this->moveOut->refresh();

        $this->assertEquals(8000, $this->moveOut->total_deductions);
        $this->assertEquals(42000, $this->moveOut->refund_amount);
    }

    public function test_no_deductions_created_when_no_always_apply_categories_exist(): void
    {
        MoveOutDeductionCategory::factory()
            ->forBuilding($this->building)
            ->optional()
            ->create(['name' => 'Optional Only']);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(0, $deductions);
    }

    public function test_platform_defaults_with_always_apply_are_not_auto_applied(): void
    {
        MoveOutDeductionCategory::factory()
            ->platformDefault()
            ->alwaysApply()
            ->create(['name' => 'Platform Default']);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.start-inspection', $this->moveOut), [
                'actual_move_out_date' => now()->toDateString(),
            ]);

        $deductions = MoveOutDeduction::where('move_out_id', $this->moveOut->id)->get();

        $this->assertCount(0, $deductions);
    }
}
