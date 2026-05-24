<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Building;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-55 DASHBOARD-FILTERS-2/3 + Phase-105 PORTFOLIO-HOME watchdog.
 *
 * Phase-105 made the landlord landing (no building_id) a cross-property PORTFOLIO overview;
 * choosing a building (the 'all' sentinel or a specific id) renders the building-scoped
 * dashboard. These tests exercise the landing (portfolio) + both building_id states.
 */
class Phase55DashboardFiltersTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_landing_renders_the_portfolio_overview(): void
    {
        // Phase-105: GET /dashboard with no building_id → the portfolio overview (not a
        // single building, and not the old service-default all-buildings mode).
        [$landlord] = $this->makeTwoBuildingFixture();

        $props = $this->actingAs($landlord)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Portfolio/Home', $props['component']);
        $this->assertArrayHasKey('kpis', $props['props']);
        $this->assertSame(1, $props['props']['kpis']['property_count']);
        $this->assertCount(1, $props['props']['properties']);
    }

    public function test_building_view_is_reached_with_a_building_id(): void
    {
        // The rich building-scoped dashboard is the drill-down (building_id present).
        [$landlord, $paymentA, , $buildingA] = $this->makeTwoBuildingFixture();

        $props = $this->actingAs($landlord)
            ->get(route('dashboard', ['building_id' => $buildingA->id]))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Dashboard', $props['component']);
        $this->assertFalse($props['props']['allBuildingsMode']);
        $this->assertContains($paymentA->id, collect($props['props']['recentPayments'])->pluck('id')->all());
    }

    public function test_building_id_all_sentinel_aggregates_metrics_across_buildings(): void
    {
        [$landlord, $paymentA, $paymentB] = $this->makeTwoBuildingFixture();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($landlord, new Request(['building_id' => 'all']));

        $this->assertTrue($data['allBuildingsMode']);
        $ids = collect($data['recentPayments'])->pluck('id')->all();
        $this->assertContains($paymentA->id, $ids);
        $this->assertContains($paymentB->id, $ids);
    }

    public function test_specific_building_id_scopes_to_that_buildings_payments_only(): void
    {
        [$landlord, $paymentA, $paymentB, $buildingA] = $this->makeTwoBuildingFixture();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($landlord, new Request(['building_id' => $buildingA->id]));

        $this->assertFalse($data['allBuildingsMode']);
        $ids = collect($data['recentPayments'])->pluck('id')->all();
        $this->assertContains($paymentA->id, $ids);
        $this->assertNotContains(
            $paymentB->id,
            $ids,
            'Specific building filter must exclude other buildings\' payments.',
        );
    }

    /**
     * @return array{0: User, 1: Payment, 2: Payment, 3: Building}
     */
    private function makeTwoBuildingFixture(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Twin Towers',
            'address' => '12 Test',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $makeBuilding = function (string $name) use ($landlord, $property): Building {
            return Building::create([
                'property_id' => $property->id,
                'name' => $name,
                'total_floors' => 1,
                'units_per_floor' => 2,
                'landlord_id' => $landlord->id,
                'building_type' => 'residential_apartment',
            ]);
        };
        $buildingA = $makeBuilding('Block A');
        $buildingB = $makeBuilding('Block B');

        $makeUnit = function (Building $building, string $number) use ($landlord): Unit {
            return Unit::create([
                'building_id' => $building->id,
                'unit_number' => $number,
                'floor_number' => 1,
                'status' => 'vacant',
                'target_rent' => 20000,
                'landlord_id' => $landlord->id,
            ]);
        };

        ['lease' => $leaseA] = $this->createTenantWithActiveLease($landlord, $makeUnit($buildingA, 'A-101'));
        ['lease' => $leaseB] = $this->createTenantWithActiveLease($landlord, $makeUnit($buildingB, 'B-101'));

        ['payment' => $paymentA] = $this->createPaymentWithInvoice($leaseA, 3000);
        ['payment' => $paymentB] = $this->createPaymentWithInvoice($leaseB, 4000);

        return [$landlord, $paymentA, $paymentB, $buildingA];
    }
}
