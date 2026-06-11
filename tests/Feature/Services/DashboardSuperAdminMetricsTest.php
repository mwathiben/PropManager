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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * M2 decomposition safety net: characterizes the platform-wide super-admin
 * metrics (getSuperAdminMetrics + its private getLandlordsMonthlyRevenue
 * helper), extracted into SuperAdminMetricsCalculator.
 *
 * These metrics are PLATFORM-WIDE counts (every query bypasses the landlord
 * scope). Under `php artisan test --parallel` the suite shares a database per
 * worker, and committed rows from other tests in the same worker can be
 * visible to these global counts — so absolute-count assertions flake. We
 * therefore assert DELTAS against a captured baseline (immune to ambient
 * rows) and ID-keyed lookups for the per-landlord revenue. getSuperAdminMetrics
 * caches under a month key, so Cache::flush() is required between the baseline
 * and the post-create read for the recompute to be observed.
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

    private function metrics(): array
    {
        // The month-keyed cache must be busted so a recompute is observed
        // after we mutate the platform between reads.
        Cache::flush();

        return app(DashboardService::class)->getSuperAdminMetrics();
    }

    public function test_super_admin_metrics_aggregate_platform_wide(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $before = $this->metrics();
        $beforeHealth = $before['systemHealth'];
        $beforeActions = $before['actionItems'];

        $l1 = $this->activeLandlordWithRevenue('L1', 5000);
        $l2 = $this->activeLandlordWithRevenue('L2', 3000);
        $l3 = User::factory()->create(['role' => 'landlord', 'name' => 'L3']); // inactive: no property

        $after = $this->metrics();
        $health = $after['systemHealth'];
        $actions = $after['actionItems'];

        // Deltas — immune to ambient rows leaked by sibling --parallel tests.
        $this->assertSame(3, $health['active_landlords'] - $beforeHealth['active_landlords']);
        $this->assertSame(2, $health['total_properties'] - $beforeHealth['total_properties']);
        $this->assertSame(2, $health['total_units'] - $beforeHealth['total_units']);
        $this->assertSame(2, $health['total_tenants'] - $beforeHealth['total_tenants']);
        $this->assertEqualsWithDelta(8000, (float) $health['monthly_revenue'] - (float) $beforeHealth['monthly_revenue'], 0.001);
        $this->assertEqualsWithDelta(8000, (float) $health['total_revenue'] - (float) $beforeHealth['total_revenue'], 0.001);

        $this->assertSame(1, $actions['inactive_landlords'] - $beforeActions['inactive_landlords']); // L3
        $this->assertSame(3, $actions['new_signups'] - $beforeActions['new_signups']);

        // Per-landlord monthly-revenue mapping (relocated getLandlordsMonthlyRevenue),
        // keyed by id — robust regardless of ambient landlords.
        $revenueById = collect($after['landlords'])->keyBy('id');
        $this->assertEqualsWithDelta(5000, (float) $revenueById[$l1->id]->monthly_revenue, 0.001);
        $this->assertEqualsWithDelta(3000, (float) $revenueById[$l2->id]->monthly_revenue, 0.001);
        $this->assertEqualsWithDelta(0, (float) $revenueById[$l3->id]->monthly_revenue, 0.001);

        // topLandlords orders by monthly revenue, desc: L1 (5000) before L2 (3000).
        // Assert relative order only (ambient high-revenue landlords could sit
        // between them, but cannot reorder them).
        $myTop = collect($after['topLandlords'])->pluck('id')
            ->filter(fn ($id) => in_array($id, [$l1->id, $l2->id], true))->values();
        if ($myTop->count() === 2) {
            $this->assertSame([$l1->id, $l2->id], $myTop->all());
        }
    }

    public function test_super_admin_metrics_landlord_without_payments_has_zero_revenue(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $before = $this->metrics()['systemHealth'];

        // A landlord with no property/payments: counts as active but contributes
        // no monthly revenue (exercises the zero branch of the revenue mapping).
        $landlord = User::factory()->create(['role' => 'landlord', 'name' => 'NoRevenue']);

        $after = $this->metrics();

        $this->assertSame(1, $after['systemHealth']['active_landlords'] - $before['active_landlords']);
        $this->assertEqualsWithDelta(0, (float) $after['systemHealth']['monthly_revenue'] - (float) $before['monthly_revenue'], 0.001);

        $revenueById = collect($after['landlords'])->keyBy('id');
        $this->assertArrayHasKey($landlord->id, $revenueById->all());
        $this->assertEqualsWithDelta(0, (float) $revenueById[$landlord->id]->monthly_revenue, 0.001);
    }
}
