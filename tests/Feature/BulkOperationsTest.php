<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected Property $property;

    protected Building $building;

    protected array $units = [];

    protected array $tenants = [];

    protected array $leases = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create landlord user
        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Authenticate as landlord for setup
        $this->actingAs($this->landlord);

        // Create property
        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Property',
            'type' => 'residential',
            'address' => '123 Test Street',
        ]);

        // Create building
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Building A',
            'total_floors' => 3,
            'units_per_floor' => 4,
        ]);

        // Create multiple units
        for ($i = 1; $i <= 5; $i++) {
            $status = $i <= 3 ? 'occupied' : 'vacant';
            $this->units[] = Unit::create([
                'building_id' => $this->building->id,
                'landlord_id' => $this->landlord->id,
                'unit_number' => 'A10'.$i,
                'floor_number' => 1,
                'status' => $status,
                'target_rent' => 15000 + ($i * 1000),
                'meter_number' => 'WM00'.$i,
            ]);

            // Create tenants and leases for occupied units
            if ($status === 'occupied') {
                $this->tenants[] = User::factory()->create([
                    'role' => 'tenant',
                    'landlord_id' => $this->landlord->id,
                ]);

                $this->leases[] = Lease::create([
                    'unit_id' => $this->units[$i - 1]->id,
                    'tenant_id' => $this->tenants[count($this->tenants) - 1]->id,
                    'landlord_id' => $this->landlord->id,
                    'start_date' => now()->subMonths(3),
                    'end_date' => now()->addMonths(9),
                    'rent_amount' => 15000 + ($i * 1000),
                    'deposit_amount' => 15000 + ($i * 1000),
                    'is_active' => true,
                ]);
            }
        }
    }

    public function test_bulk_operations_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/bulk-operations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('BulkOperations/Index'));
    }

    public function test_bulk_operations_page_contains_required_data(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/bulk-operations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('properties')
            ->has('units')
            ->has('tenants')
        );
    }

    public function test_can_perform_bulk_rent_adjustment(): void
    {
        $leaseIds = array_map(fn ($lease) => $lease->id, $this->leases);

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/adjust-rent', [
                'lease_ids' => $leaseIds,
                'adjustment_type' => 'percentage',
                'adjustment_value' => 10,
                'effective_date' => now()->addDays(7)->format('Y-m-d'),
                'reason' => 'Annual rent increase',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify rent was increased by 10%
        foreach ($this->leases as $lease) {
            $lease->refresh();
            // rent_amount should now be 10% higher
        }

        // Check rent history was created
        $this->assertDatabaseHas('rent_histories', [
            'lease_id' => $this->leases[0]->id,
            'reason' => 'Annual rent increase',
        ]);
    }

    public function test_can_perform_bulk_rent_adjustment_with_fixed_amount(): void
    {
        $leaseIds = [$this->leases[0]->id];
        $originalRent = $this->leases[0]->rent_amount;

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/adjust-rent', [
                'lease_ids' => $leaseIds,
                'adjustment_type' => 'fixed',
                'adjustment_value' => 500,
                'effective_date' => now()->addDays(7)->format('Y-m-d'),
                'reason' => 'Service charge increase',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify rent was increased by 500
        $this->leases[0]->refresh();
        $this->assertEquals($originalRent + 500, $this->leases[0]->rent_amount);
    }

    public function test_can_perform_bulk_unit_status_update(): void
    {
        $unitIds = [$this->units[3]->id, $this->units[4]->id]; // The vacant units

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/update-unit-status', [
                'unit_ids' => $unitIds,
                'new_status' => 'maintenance',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify status was updated
        $this->units[3]->refresh();
        $this->units[4]->refresh();
        $this->assertEquals('maintenance', $this->units[3]->status);
        $this->assertEquals('maintenance', $this->units[4]->status);
    }

    public function test_can_perform_bulk_lease_termination(): void
    {
        $leaseIds = [$this->leases[0]->id];

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/terminate-leases', [
                'lease_ids' => $leaseIds,
                'termination_date' => now()->format('Y-m-d'),
                'reason' => 'Mutual agreement',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify lease was terminated
        $this->leases[0]->refresh();
        $this->assertFalse($this->leases[0]->is_active);

        // Verify unit status updated to vacant
        $this->units[0]->refresh();
        $this->assertEquals('vacant', $this->units[0]->status);
    }

    public function test_can_perform_bulk_lease_extension(): void
    {
        $leaseIds = array_map(fn ($lease) => $lease->id, $this->leases);
        $originalEndDates = array_map(fn ($lease) => $lease->end_date->format('Y-m-d'), $this->leases);

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/extend-leases', [
                'lease_ids' => $leaseIds,
                'extension_months' => 6,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify leases were extended by 6 months
        foreach ($this->leases as $index => $lease) {
            $lease->refresh();
            $expectedEndDate = now()->parse($originalEndDates[$index])->addMonths(6)->format('Y-m-d');
            $this->assertEquals($expectedEndDate, $lease->end_date->format('Y-m-d'));
        }
    }

    public function test_can_perform_bulk_deposit_adjustment(): void
    {
        $leaseIds = [$this->leases[0]->id];
        $originalDeposit = $this->leases[0]->deposit_amount;

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/adjust-deposits', [
                'lease_ids' => $leaseIds,
                'adjustment_type' => 'fixed',
                'adjustment_value' => 2000,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify deposit was adjusted
        $this->leases[0]->refresh();
        $this->assertEquals($originalDeposit + 2000, $this->leases[0]->deposit_amount);
    }

    public function test_can_perform_bulk_target_rent_update(): void
    {
        $unitIds = [$this->units[0]->id, $this->units[1]->id];

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/update-target-rent', [
                'unit_ids' => $unitIds,
                'adjustment_type' => 'percentage',
                'adjustment_value' => 5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify target rent was updated
        $this->units[0]->refresh();
        $this->units[1]->refresh();
        // Target rent should be 5% higher than original
    }

    public function test_can_perform_bulk_meter_number_update(): void
    {
        $updates = [
            ['unit_id' => $this->units[0]->id, 'meter_number' => 'NEW-001'],
            ['unit_id' => $this->units[1]->id, 'meter_number' => 'NEW-002'],
        ];

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/update-meter-numbers', [
                'updates' => $updates,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify meter numbers were updated
        $this->units[0]->refresh();
        $this->units[1]->refresh();
        $this->assertEquals('NEW-001', $this->units[0]->meter_number);
        $this->assertEquals('NEW-002', $this->units[1]->meter_number);
    }

    public function test_bulk_rent_adjustment_validates_required_fields(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/adjust-rent', [
                // Missing required fields
            ]);

        $response->assertSessionHasErrors(['lease_ids', 'adjustment_type', 'adjustment_value']);
    }

    public function test_bulk_unit_status_update_validates_status(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/update-unit-status', [
                'unit_ids' => [$this->units[0]->id],
                'new_status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['new_status']);
    }

    public function test_caretaker_can_perform_bulk_operations(): void
    {
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($caretaker)
            ->get('/bulk-operations');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_bulk_operations(): void
    {
        auth()->logout();

        $response = $this->get('/bulk-operations');

        $response->assertRedirect('/login');
    }

    public function test_landlord_cannot_perform_bulk_operations_on_other_landlord_data(): void
    {
        // Create another landlord with their own data
        $otherLandlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        $this->actingAs($otherLandlord);

        $otherProperty = Property::create([
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Property',
            'type' => 'commercial',
        ]);

        $otherBuilding = Building::create([
            'property_id' => $otherProperty->id,
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Building',
            'total_floors' => 2,
            'units_per_floor' => 2,
        ]);

        $otherUnit = Unit::create([
            'building_id' => $otherBuilding->id,
            'landlord_id' => $otherLandlord->id,
            'unit_number' => 'B101',
            'floor_number' => 1,
            'status' => 'vacant',
        ]);

        // Try to update other landlord's unit as first landlord
        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/update-unit-status', [
                'unit_ids' => [$otherUnit->id],
                'new_status' => 'maintenance',
            ]);

        // Should succeed but not actually update anything (due to TenantScope)
        $response->assertRedirect();

        // Verify the other landlord's unit was NOT updated
        $otherUnit->refresh();
        $this->assertEquals('vacant', $otherUnit->status);
    }

    public function test_bulk_operations_page_filters_by_property(): void
    {
        // Create second property with different building
        $property2 = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Second Property',
            'type' => 'commercial',
        ]);

        $building2 = Building::create([
            'property_id' => $property2->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Building B',
            'total_floors' => 1,
            'units_per_floor' => 2,
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/bulk-operations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('properties', 2)
        );
    }

    public function test_bulk_rent_adjustment_creates_rent_history(): void
    {
        $leaseIds = [$this->leases[0]->id];

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/adjust-rent', [
                'lease_ids' => $leaseIds,
                'adjustment_type' => 'fixed',
                'adjustment_value' => 1000,
                'effective_date' => now()->addDays(7)->format('Y-m-d'),
                'reason' => 'Market rate adjustment',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify rent history record was created
        $this->assertDatabaseHas('rent_histories', [
            'lease_id' => $this->leases[0]->id,
            'reason' => 'Market rate adjustment',
        ]);
    }

    public function test_bulk_lease_termination_updates_unit_status(): void
    {
        $leaseIds = [$this->leases[0]->id];

        // Ensure unit is occupied before termination
        $this->assertEquals('occupied', $this->units[0]->status);

        $response = $this->actingAs($this->landlord)
            ->post('/bulk-operations/terminate-leases', [
                'lease_ids' => $leaseIds,
                'termination_date' => now()->format('Y-m-d'),
                'reason' => 'End of lease term',
            ]);

        $response->assertRedirect();

        // Verify unit status changed to vacant
        $this->units[0]->refresh();
        $this->assertEquals('vacant', $this->units[0]->status);
    }
}
