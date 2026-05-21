<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Property\PropertyBenchmarkService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-78 PROPERTY-BENCHMARK: cross-property percentile ranking + portfolio
 * medians + rollup gauge.
 */
class Phase78PropertyBenchmarkTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Property $property;

    private $units;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->property = $setup['property'];
        $this->units = $setup['units'];
        $this->actingAs($this->landlord);
    }

    /** @return array{property: Property, units: \Illuminate\Support\Collection} */
    private function secondProperty(): array
    {
        return Model::withoutEvents(function () {
            $property = Property::create([
                'landlord_id' => $this->landlord->id,
                'name' => 'Second Estate',
                'type' => 'residential',
                'address' => 'Y',
            ]);
            $building = Building::create([
                'property_id' => $property->id,
                'landlord_id' => $this->landlord->id,
                'name' => 'Block B',
                'total_floors' => 1,
                'units_per_floor' => 8,
                'building_type' => 'residential_apartment',
            ]);
            $units = collect();
            for ($i = 1; $i <= 8; $i++) {
                $units->push(Unit::create([
                    'building_id' => $building->id,
                    'landlord_id' => $this->landlord->id,
                    'unit_number' => "B10{$i}",
                    'floor_number' => 1,
                    'status' => 'vacant',
                    'target_rent' => 25000,
                ]));
            }

            return compact('property', 'units');
        });
    }

    public function test_higher_occupancy_property_ranks_first(): void
    {
        $second = $this->secondProperty();

        // Property A: 4/8 occupied. Property B: 1/8 occupied.
        Model::withoutEvents(function () use ($second) {
            for ($i = 0; $i < 4; $i++) {
                $this->createTenantWithActiveLease($this->landlord, $this->units->get($i));
            }
            $this->createTenantWithActiveLease($this->landlord, $second['units']->get(0));
        });

        $result = app(PropertyBenchmarkService::class)->forLandlord($this->landlord->id);
        $rows = collect($result['properties'])->keyBy('property_id');

        $a = $rows[$this->property->id];
        $b = $rows[$second['property']->id];

        $this->assertSame(1, $a['rank']);
        $this->assertSame(2, $b['rank']);
        $this->assertSame(100.0, $a['occupancy_percentile']);
        $this->assertSame(0.0, $b['occupancy_percentile']);
    }

    public function test_single_property_has_null_percentiles_and_rank_one(): void
    {
        $result = app(PropertyBenchmarkService::class)->forLandlord($this->landlord->id);

        $this->assertCount(1, $result['properties']);
        $row = $result['properties'][0];
        $this->assertNull($row['occupancy_percentile']);
        $this->assertSame(1, $row['rank']);
    }

    public function test_gross_yield_uses_annualised_rent_over_estimated_value(): void
    {
        $this->property->update(['estimated_value' => 12_000_000]);
        Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $this->units->get(0)));

        $result = app(PropertyBenchmarkService::class)->forLandlord($this->landlord->id);
        $row = collect($result['properties'])->firstWhere('property_id', $this->property->id);

        // 25000 * 12 / 12_000_000 = 0.025
        $this->assertEqualsWithDelta(0.025, $row['gross_yield'], 0.0001);
    }

    public function test_benchmark_route_renders_with_portfolio(): void
    {
        $response = $this->get(route('properties.benchmark'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('properties', $props);
        $this->assertArrayHasKey('portfolio', $props);
        $this->assertSame(1, $props['portfolio']['property_count']);
    }

    public function test_benchmark_rollup_command_exits_zero(): void
    {
        $this->artisan('property:benchmark-rollup')->assertExitCode(0);
    }
}
