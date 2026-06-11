<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * M2 decomposition safety net: characterizes the arrears-aging money math
 * (getArrearsInRangeForLeases + getArrearsAgingBucketsForLeases) BEFORE it
 * is extracted out of the 1153-line DashboardService. These assert the
 * current behaviour so the extraction is provably behaviour-preserving.
 */
class DashboardArrearsTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Arrears Property', 'address' => '1 Arrears St',
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
        $this->lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'landlord_id' => $landlord->id,
            'rent_amount' => 10000, 'deposit_amount' => 10000, 'start_date' => now()->subYear(),
            'is_active' => true, 'wallet_balance' => 0,
        ]);

        // Outstanding invoices at deliberate ages (arrears = total_due - amount_paid):
        $this->invoice(dueDaysAgo: 10, total: 1000, paid: 0, status: 'overdue');   // 0_30  -> 1000
        $this->invoice(dueDaysAgo: 45, total: 2000, paid: 500, status: 'partial'); // 31_60 -> 1500
        $this->invoice(dueDaysAgo: 75, total: 800, paid: 0, status: 'overdue');    // 61_90 -> 800
        $this->invoice(dueDaysAgo: 120, total: 3000, paid: 0, status: 'overdue');  // 90+   -> 3000
        // Noise that must be EXCLUDED:
        $this->invoice(dueDaysAgo: 20, total: 5000, paid: 5000, status: 'paid');   // paid status
        $this->invoice(dueDaysAgo: -5, total: 700, paid: 0, status: 'sent');       // not overdue/partial
    }

    private function invoice(int $dueDaysAgo, float $total, float $paid, string $status): void
    {
        Invoice::create([
            'lease_id' => $this->lease->id, 'landlord_id' => $this->lease->landlord_id,
            'invoice_number' => 'INV-ARR-'.uniqid(),
            'due_date' => now()->subDays($dueDaysAgo),
            'billing_period_start' => now()->subDays($dueDaysAgo)->startOfMonth(),
            'rent_due' => $total, 'water_due' => 0, 'arrears' => 0,
            'total_due' => $total, 'amount_paid' => $paid, 'wallet_applied' => 0,
            'status' => $status,
        ]);
    }

    private function ids(): Collection
    {
        return collect([$this->lease->id]);
    }

    public function test_arrears_in_range_for_leases_sums_only_outstanding_in_window(): void
    {
        $this->assertSame(1000.0, (float) $this->service->getArrearsInRangeForLeases($this->ids(), 0, 30));
        $this->assertSame(1500.0, (float) $this->service->getArrearsInRangeForLeases($this->ids(), 31, 60));
        $this->assertSame(3000.0, (float) $this->service->getArrearsInRangeForLeases($this->ids(), 91, 9999));
    }

    public function test_arrears_in_range_for_leases_is_zero_for_empty_lease_set(): void
    {
        $this->assertSame(0.0, (float) $this->service->getArrearsInRangeForLeases(collect(), 0, 30));
    }

    public function test_arrears_aging_buckets_split_outstanding_by_age(): void
    {
        $buckets = $this->service->getArrearsAgingBucketsForLeases($this->ids());

        $this->assertSame(1000.0, $buckets['0_30']);
        $this->assertSame(1500.0, $buckets['31_60']);
        $this->assertSame(800.0, $buckets['61_90']);
        $this->assertSame(3000.0, $buckets['90_plus']);
    }

    public function test_arrears_aging_buckets_empty_for_no_leases(): void
    {
        $this->assertSame(
            ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0],
            $this->service->getArrearsAgingBucketsForLeases(collect()),
        );
    }
}
