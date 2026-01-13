<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\LateFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Building $building;

    private Unit $unit;

    private Lease $lease;

    private Invoice $invoice;

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
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 20000,
            'deposit_amount' => 20000,
            'start_date' => now()->subMonths(3),
            'is_active' => true,
        ]);

        $this->invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-202601-0001',
            'due_date' => now()->subDays(10),
            'billing_period_start' => now()->subMonth(),
            'rent_due' => 20000,
            'water_due' => 500,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 20500,
            'amount_paid' => 0,
            'status' => 'overdue',
        ]);
    }

    public function test_can_create_late_fee_policy(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.late-fee-policies.store'), [
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('late_fee_policies', [
            'name' => 'Default Late Fee',
            'landlord_id' => $this->landlord->id,
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
        ]);
    }

    public function test_late_fee_service_calculates_percentage_fee(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $fee = $policy->calculateFee(20500, 0);

        $this->assertEquals(1025.00, $fee);
    }

    public function test_late_fee_service_calculates_flat_fee(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Flat Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'flat_amount',
            'fee_amount' => 500.00,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $fee = $policy->calculateFee(20500, 0);

        $this->assertEquals(500.00, $fee);
    }

    public function test_late_fee_respects_max_cap(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Capped Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 50.0,
            'is_compounding' => false,
            'max_fee_cap' => 1000.00,
            'is_active' => true,
            'priority' => 10,
        ]);

        $fee = $policy->calculateFee(20500, 0);

        $this->assertEquals(1000.00, $fee);
    }

    public function test_late_fee_service_applies_fee_to_invoice(): void
    {
        LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $service = app(LateFeeService::class);
        $lateFee = $service->applyLateFee($this->invoice);

        $this->assertNotNull($lateFee);
        $this->assertEquals(1025.00, $lateFee->fee_amount);

        $this->invoice->refresh();
        $this->assertEquals(1025.00, $this->invoice->late_fees_total);
        $this->assertEquals(21525.00, $this->invoice->total_due);
    }

    public function test_late_fee_not_applied_within_grace_period(): void
    {
        LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 15,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $service = app(LateFeeService::class);
        $lateFee = $service->applyLateFee($this->invoice);

        $this->assertNull($lateFee);
    }

    public function test_late_fee_can_be_waived(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $lateFee = LateFee::create([
            'invoice_id' => $this->invoice->id,
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $this->landlord->id,
            'fee_amount' => 1025.00,
            'cumulative_total' => 1025.00,
            'applied_date' => now(),
            'days_overdue' => 10,
        ]);

        $this->invoice->update([
            'late_fees_total' => 1025.00,
            'total_due' => 21525.00,
        ]);

        $service = app(LateFeeService::class);
        $result = $service->waiveLateFee($lateFee, $this->landlord->id, 'Customer goodwill');

        $this->assertTrue($result);

        $lateFee->refresh();
        $this->assertTrue($lateFee->is_waived);
        $this->assertEquals('Customer goodwill', $lateFee->waiver_reason);

        $this->invoice->refresh();
        $this->assertEquals(0, $this->invoice->late_fees_total);
        $this->assertEquals(1025.00, $this->invoice->late_fees_waived);
    }

    public function test_policy_hierarchy_building_over_property(): void
    {
        LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'name' => 'Property Level Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 20,
        ]);

        $buildingPolicy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'building_id' => $this->building->id,
            'name' => 'Building Level Fee',
            'grace_period_days' => 3,
            'fee_type' => 'percentage',
            'fee_percentage' => 10.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 30,
        ]);

        $service = app(LateFeeService::class);
        $policy = $service->getPolicyForInvoice($this->invoice);

        $this->assertEquals($buildingPolicy->id, $policy->id);
        $this->assertEquals('Building Level Fee', $policy->name);
    }

    public function test_controller_can_toggle_policy_status(): void
    {
        $this->actingAs($this->landlord);

        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Policy',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $response = $this->post(route('finances.late-fee-policies.toggle', $policy));

        $response->assertRedirect();

        $policy->refresh();
        $this->assertFalse($policy->is_active);
    }

    public function test_tenant_isolation_for_late_fee_policies(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        Property::create([
            'name' => 'Other Property',
            'address' => '456 Other St',
            'type' => 'apartment',
            'landlord_id' => $otherLandlord->id,
        ]);

        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Other Landlord Policy',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5.0,
            'is_compounding' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        $this->actingAs($otherLandlord);

        $response = $this->post(route('finances.late-fee-policies.toggle', $policy));

        $response->assertStatus(403);

        $policy->refresh();
        $this->assertTrue($policy->is_active);
    }
}
