<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M2 decomposition safety net: characterizes the platform-wide super-admin
 * metrics (getSuperAdminMetrics + its private getLandlordsMonthlyRevenue
 * helper) BEFORE they are extracted out of DashboardService. Locks the
 * system-health counts, action items, and the per-landlord monthly-revenue
 * mapping so the extraction is provably behaviour-preserving — and adds
 * net-new coverage for the platform financial dashboard.
 */
class DashboardSuperAdminMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function activeLandlordWithRevenue(string $name, float $paid): User
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'name' => $name]);
        $property = Property::create([
            'name' => "{$name} Property", 'address' => "1 {$name} St",
            'type' => 'apartment', 'landlord_id' => $landlord->id,
        ]);
        $building = Building::create([
            'property_id' => $property->id, 'name' => 'Block A',
            'total_floors' => 1, 'units_per_floor' => 1,
            'landlord_id' => $landlord->id, 'building_type' => 'residential_apartment',
        ]);
        $unit = Unit::create([
            'building_id' => $building->id, 'unit_number' => 'A1', 'floor_number' => 1,
            'status' => 'occupied', 'target_rent' => 10000, 'landlord_id' => $landlord->id,
        ]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'landlord_id' => $landlord->id,
            'rent_amount' => 10000, 'deposit_amount' => 10000, 'start_date' => now()->subMonths(2),
            'is_active' => true, 'wallet_balance' => 0,
        ]);
        $invoice = Invoice::create([
            'lease_id' => $lease->id, 'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-SA-'.uniqid(),
            'due_date' => now(), 'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 10000, 'water_due' => 0, 'arrears' => 0,
            'total_due' => 10000, 'amount_paid' => $paid, 'wallet_applied' => 0,
            'status' => 'partial',
        ]);
        Payment::create([
            'invoice_id' => $invoice->id, 'lease_id' => $lease->id, 'landlord_id' => $landlord->id,
            'amount' => $paid, 'payment_method' => 'cash', 'payment_date' => now(),
            'reference' => 'PAY-'.uniqid(),
        ]);

        return $landlord;
    }

    public function test_super_admin_metrics_aggregate_platform_wide(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $l1 = $this->activeLandlordWithRevenue('L1', 5000);
        $l2 = $this->activeLandlordWithRevenue('L2', 3000);
        $l3 = User::factory()->create(['role' => 'landlord', 'name' => 'L3']); // inactive: no property

        $metrics = app(DashboardService::class)->getSuperAdminMetrics();

        $health = $metrics['systemHealth'];
        $this->assertSame(3, $health['active_landlords']);
        $this->assertSame(2, $health['total_properties']);
        $this->assertSame(2, $health['total_units']);
        $this->assertSame(2, $health['total_tenants']);
        $this->assertEqualsWithDelta(8000, (float) $health['monthly_revenue'], 0.001);
        $this->assertEqualsWithDelta(8000, (float) $health['total_revenue'], 0.001);

        $actions = $metrics['actionItems'];
        $this->assertSame(1, $actions['inactive_landlords']);
        $this->assertSame(3, $actions['new_signups']);

        // Exercises the relocated getLandlordsMonthlyRevenue grouping.
        $revenueById = collect($metrics['landlords'])->keyBy('id');
        $this->assertCount(3, $revenueById);
        $this->assertEqualsWithDelta(5000, (float) $revenueById[$l1->id]->monthly_revenue, 0.001);
        $this->assertEqualsWithDelta(3000, (float) $revenueById[$l2->id]->monthly_revenue, 0.001);
        $this->assertEqualsWithDelta(0, (float) $revenueById[$l3->id]->monthly_revenue, 0.001);

        // topLandlords is ordered by monthly revenue, descending.
        $this->assertSame($l1->id, $metrics['topLandlords']->first()->id);
    }

    public function test_super_admin_metrics_on_empty_platform(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $metrics = app(DashboardService::class)->getSuperAdminMetrics();

        $this->assertSame(0, $metrics['systemHealth']['active_landlords']);
        $this->assertSame(0, $metrics['systemHealth']['total_properties']);
        $this->assertEqualsWithDelta(0, (float) $metrics['systemHealth']['monthly_revenue'], 0.001);
        $this->assertSame(0, $metrics['actionItems']['inactive_landlords']);
        $this->assertCount(0, $metrics['landlords']);
        $this->assertCount(0, $metrics['topLandlords']);
    }
}
