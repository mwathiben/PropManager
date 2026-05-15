<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-27 BI-FORECAST-1/2/3 watchdogs: rent-roll shape, seasonality
 * fallback for thin data, vacancy projection sorted by impact.
 */
class Phase27ForecastTest extends TestCase
{
    use RefreshDatabase;

    private ForecastService $service;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ForecastService::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function unit(?float $targetRent = 25000, string $status = 'occupied'): Unit
    {
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $building = Building::factory()->create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $property->id,
        ]);

        return Unit::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $building->id,
            'target_rent' => $targetRent,
            'status' => $status,
        ]);
    }

    private function activeLeaseOn(Unit $unit, float $rent, Carbon $startedAt): Lease
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => $rent,
            'start_date' => $startedAt,
            'is_active' => true,
        ]);
    }

    private function payment(Lease $lease, float $amount, Carbon $when): Payment
    {
        return Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => $amount,
            'currency' => 'KES',
            'payment_method' => 'cash',
            'payment_date' => $when,
            'reference' => 'PAY-'.uniqid(),
        ]);
    }

    public function test_rent_roll_returns_one_row_per_month(): void
    {
        $unit = $this->unit();
        $this->activeLeaseOn($unit, 25000, Carbon::now()->subMonth());

        $forecast = $this->service->rentRoll($this->landlord->id, 6);

        $this->assertCount(6, $forecast['months']);
        foreach ($forecast['months'] as $row) {
            $this->assertArrayHasKey('month', $row);
            $this->assertArrayHasKey('active_rent', $row);
            $this->assertArrayHasKey('expected_revenue', $row);
            $this->assertArrayHasKey('low_estimate', $row);
            $this->assertArrayHasKey('high_estimate', $row);
            $this->assertArrayHasKey('seasonality', $row);
        }
    }

    public function test_rent_roll_active_rent_reflects_lease_lifecycle(): void
    {
        $unit = $this->unit();
        $this->activeLeaseOn($unit, 30000, Carbon::now()->subMonth());

        // Second lease that ends in 2 months.
        $shortUnit = $this->unit();
        $this->activeLeaseOn($shortUnit, 20000, Carbon::now()->subMonth())
            ->update(['end_date' => Carbon::now()->addMonths(2)->endOfMonth()]);

        $forecast = $this->service->rentRoll($this->landlord->id, 6);
        $months = $forecast['months'];

        // First forecast month (+1): both leases active → 50000.
        $this->assertSame(50000.0, $months[0]['active_rent']);

        // Month +4 onward: only the open-ended lease → 30000.
        $this->assertSame(30000.0, $months[3]['active_rent']);
    }

    public function test_rent_roll_clamps_collection_rate_into_sane_range(): void
    {
        $this->unit();
        $forecast = $this->service->rentRoll($this->landlord->id, 3);

        $this->assertGreaterThanOrEqual(0.5, $forecast['collection_rate']);
        $this->assertLessThanOrEqual(1.0, $forecast['collection_rate']);
    }

    public function test_seasonality_factor_falls_back_to_one_with_thin_history(): void
    {
        $factor = $this->service->seasonalityFactor($this->landlord->id, 6);
        $this->assertSame(1.0, $factor, 'BI-FORECAST-2: < 12 months history must produce factor 1.0.');
    }

    public function test_seasonality_factor_reflects_high_month_with_dense_history(): void
    {
        $unit = $this->unit();
        $lease = $this->activeLeaseOn($unit, 10000, Carbon::now()->subYears(2));

        // Seed 15 months of payments. December (month 12) gets 2x; others get 1x.
        for ($i = 0; $i < 15; $i++) {
            $date = Carbon::now()->subYears(2)->addMonths($i)->day(5);
            $amount = $date->month === 12 ? 20000 : 10000;
            $this->payment($lease, (float) $amount, $date);
        }

        $december = $this->service->seasonalityFactor($this->landlord->id, 12);
        $january = $this->service->seasonalityFactor($this->landlord->id, 1);

        $this->assertGreaterThan(1.3, $december, 'BI-FORECAST-2: December factor must exceed average given 2x payments.');
        $this->assertLessThan(1.0, $january, 'BI-FORECAST-2: January factor must be below average.');
    }

    public function test_seasonality_factor_returns_one_for_invalid_month(): void
    {
        $this->assertSame(1.0, $this->service->seasonalityFactor($this->landlord->id, 13));
        $this->assertSame(1.0, $this->service->seasonalityFactor($this->landlord->id, 0));
    }

    public function test_vacancy_projection_sorted_by_lost_revenue_desc(): void
    {
        $expensive = $this->unit(80000, 'vacant');
        $cheap = $this->unit(15000, 'vacant');

        $rows = $this->service->vacancyProjection($this->landlord->id);

        $this->assertGreaterThanOrEqual(2, count($rows));
        $expensiveRow = collect($rows)->firstWhere('unit_id', $expensive->id);
        $cheapRow = collect($rows)->firstWhere('unit_id', $cheap->id);

        $this->assertGreaterThan($cheapRow['lost_revenue_kes'], $expensiveRow['lost_revenue_kes']);
        $this->assertSame($expensive->id, $rows[0]['unit_id'], 'BI-FORECAST-3: highest lost-revenue must surface first.');
    }

    public function test_vacancy_projection_omits_occupied_units(): void
    {
        $vacant = $this->unit(20000, 'vacant');
        $occupied = $this->unit(40000, 'occupied');

        $rows = $this->service->vacancyProjection($this->landlord->id);
        $unitIds = array_column($rows, 'unit_id');

        $this->assertContains($vacant->id, $unitIds);
        $this->assertNotContains($occupied->id, $unitIds);
    }

    public function test_vacancy_projection_includes_vacant_since_when_prior_lease_existed(): void
    {
        $unit = $this->unit(25000, 'vacant');
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $endDate = Carbon::now()->subDays(20);
        Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => $endDate->copy()->subMonths(6),
            'end_date' => $endDate,
            'is_active' => false,
        ]);

        $rows = $this->service->vacancyProjection($this->landlord->id);
        $row = collect($rows)->firstWhere('unit_id', $unit->id);

        $this->assertSame($endDate->toDateString(), $row['vacant_since']);
    }

    public function test_forecast_route_renders_inertia_page(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('reports.forecast'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Forecast')
                ->has('rentRoll')
                ->has('vacancyProjection'),
            );
    }

    public function test_forecast_route_rejects_tenant_role(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('reports.forecast'))
            ->assertForbidden();
    }
}
