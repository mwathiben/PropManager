<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-105 PORTFOLIO-HOME: the landlord landing is a cross-property overview that drills
 * into building dashboards. KPIs aggregate the landlord's OWN properties only.
 */
class Phase105PortfolioHomeTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landing_renders_the_portfolio_overview(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $props = $this->actingAs($setup['landlord']->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Portfolio/Home', $props['component']);
        $this->assertArrayHasKey('kpis', $props['props']);
        $this->assertArrayHasKey('actions', $props['props']);
        $this->assertArrayHasKey('properties', $props['props']);
    }

    public function test_kpis_aggregate_across_the_landlords_properties(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        // A second property for the same landlord.
        Property::factory()->create(['landlord_id' => $landlord->id, 'name' => 'Second Property']);

        $data = app(DashboardService::class)->getPortfolioOverview($landlord->fresh());

        $this->assertSame(2, $data['kpis']['property_count']);
        $this->assertCount(2, $data['properties']);
        // Unit-weighted occupancy is a sane percentage.
        $this->assertGreaterThanOrEqual(0, $data['kpis']['occupancy_pct']);
        $this->assertLessThanOrEqual(100, $data['kpis']['occupancy_pct']);
    }

    public function test_portfolio_only_includes_the_landlords_own_properties(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        Property::factory()->create(['landlord_id' => $other['landlord']->id, 'name' => 'Foreign Tower']);

        $data = app(DashboardService::class)->getPortfolioOverview($setup['landlord']->fresh());

        $names = collect($data['properties'])->pluck('name')->all();
        $this->assertNotContains('Foreign Tower', $names);
    }

    public function test_building_id_drills_into_the_building_dashboard(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $props = $this->actingAs($setup['landlord']->fresh())
            ->get(route('dashboard', ['building_id' => $setup['building']->id]))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Dashboard', $props['component']);
    }

    public function test_landlord_without_properties_is_redirected_to_onboarding(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.index'));
    }

    public function test_a_property_carries_a_primary_building_id_for_drilldown(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $data = app(DashboardService::class)->getPortfolioOverview($setup['landlord']->fresh());
        $row = collect($data['properties'])->firstWhere('property_id', $setup['property']->id);

        $this->assertNotNull($row);
        $this->assertSame($setup['building']->id, $row['primary_building_id']);
    }
}
