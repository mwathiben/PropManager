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

class MoveOutDeductionManagementTest extends TestCase
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
            ->inspectionPending()
            ->create();
    }

    public function test_can_add_deduction_with_category(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forLandlord($this->landlord)
            ->create([
                'name' => 'Paint Works',
                'default_amount' => 5000,
            ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.deductions.store', $this->moveOut), [
                'category_id' => $category->id,
                'description' => 'Living room repaint required',
                'amount' => 5000,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('move_out_deductions', [
            'move_out_id' => $this->moveOut->id,
            'category_id' => $category->id,
            'description' => 'Living room repaint required',
            'amount' => 5000,
            'auto_applied' => false,
        ]);
    }

    public function test_can_add_deduction_without_category(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.deductions.store', $this->moveOut), [
                'description' => 'Custom damage repair',
                'amount' => 2500,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('move_out_deductions', [
            'move_out_id' => $this->moveOut->id,
            'category_id' => null,
            'description' => 'Custom damage repair',
            'amount' => 2500,
        ]);
    }

    public function test_category_id_must_exist_in_database(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.deductions.store', $this->moveOut), [
                'category_id' => 99999,
                'description' => 'Invalid category',
                'amount' => 1000,
            ]);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_deduction_with_category_has_relationship_loaded(): void
    {
        $category = MoveOutDeductionCategory::factory()
            ->forLandlord($this->landlord)
            ->paintWorks()
            ->create();

        $this->actingAs($this->landlord)
            ->post(route('move-outs.deductions.store', $this->moveOut), [
                'category_id' => $category->id,
                'description' => $category->name,
                'amount' => $category->default_amount,
            ]);

        $deduction = MoveOutDeduction::where('move_out_id', $this->moveOut->id)
            ->with('category')
            ->first();

        $this->assertNotNull($deduction->category);
        $this->assertEquals('Paint Works', $deduction->category->name);
    }

    public function test_refund_is_recalculated_when_deduction_added(): void
    {
        $this->moveOut->update([
            'deposit_held' => 50000,
            'refund_amount' => 50000,
            'total_deductions' => 0,
        ]);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.deductions.store', $this->moveOut), [
                'description' => 'Damage repair',
                'amount' => 10000,
            ]);

        $this->moveOut->refresh();

        $this->assertEquals(10000, $this->moveOut->total_deductions);
        $this->assertEquals(40000, $this->moveOut->refund_amount);
    }
}
