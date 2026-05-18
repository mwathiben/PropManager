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
 * Phase-55 DASHBOARD-FILTERS-1/2/3 watchdog.
 *
 * Two main buildings each carry a tenant + invoice + paid payment. The
 * test exercises every legal building_id state: missing (default to all),
 * 'all' sentinel, and a specific id.
 */
class Phase55DashboardFiltersTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_missing_building_id_defaults_to_all_buildings_when_multiple_main_buildings(): void
    {
        [$landlord, $paymentA, $paymentB] = $this->makeTwoBuildingFixture();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($landlord, new Request);

        $this->assertTrue($data['allBuildingsMode'], 'No filter + 2 main buildings should imply all-mode.');
        $ids = collect($data['recentPayments'])->pluck('id')->all();
        $this->assertContains($paymentA->id, $ids);
        $this->assertContains($paymentB->id, $ids);
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
