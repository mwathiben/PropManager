<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyMetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-78 PROPERTY-METRICS: per-property counts/occupancy/rent-roll/arrears,
 * landlord-scoped.
 */
class Phase78PropertyMetricsTest extends TestCase
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

    public function test_for_property_counts_occupancy_and_rent_roll(): void
    {
        Model::withoutEvents(function () {
            $this->createTenantWithActiveLease($this->landlord, $this->units->get(0));
            $this->createTenantWithActiveLease($this->landlord, $this->units->get(1));
        });

        $m = app(PropertyMetricsService::class)->forProperty($this->property);

        $this->assertSame(1, $m['building_count']);
        $this->assertSame(8, $m['unit_count']);
        $this->assertSame(2, $m['occupied_count']);
        $this->assertEqualsWithDelta(25.0, $m['occupancy_pct'], 0.1);
        $this->assertEqualsWithDelta(50000.0, $m['monthly_rent_roll'], 0.01);
    }

    public function test_arrears_sums_outstanding_invoices(): void
    {
        $lease = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $this->units->get(0))['lease']);
        Model::withoutEvents(fn () => Invoice::factory()->forLease($lease)->create([
            'total_due' => 10000,
            'amount_paid' => 3000,
            'status' => 'partial',
        ]));

        $m = app(PropertyMetricsService::class)->forProperty($this->property);

        $this->assertEqualsWithDelta(7000.0, $m['outstanding_arrears'], 0.01);
    }

    public function test_for_landlord_returns_one_row_per_property(): void
    {
        Model::withoutEvents(fn () => Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Second Estate',
            'type' => 'residential',
            'address' => 'X',
        ]));

        $rows = app(PropertyMetricsService::class)->forLandlord($this->landlord->id);

        $this->assertCount(2, $rows);
    }

    public function test_cross_tenant_property_excluded(): void
    {
        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);

        $rows = app(PropertyMetricsService::class)->forLandlord($this->landlord->id);

        $this->assertCount(1, $rows);
        $this->assertSame($this->property->id, $rows[0]['property_id']);
    }
}
