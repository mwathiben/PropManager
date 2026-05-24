<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Controllers\DashboardPreferenceController;
use App\Models\Building;
use App\Models\LandlordDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-74 CROSS-BUILDING: the landlord's main-dashboard building scope
 * (active_building | all_buildings) persists on the main_dashboard pref row,
 * defaults the dashboard view, and is overridable by an explicit ?building_id.
 */
class Phase74CrossBuildingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        // Phase-105: scope drives the BUILDING dashboard (the landing is now the portfolio),
        // so the scope tests drill into a building via ?building_id.
        $this->building = $setup['building'];

        // A second building so all-buildings mode is meaningful.
        Building::create([
            'property_id' => $setup['property']->id,
            'name' => 'Block B',
            'total_floors' => 1,
            'units_per_floor' => 2,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);
    }

    public function test_scope_defaults_to_active_building(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('dashboard', ['building_id' => $this->building->id]))
            ->assertInertia(fn ($page) => $page
                ->where('allBuildingsMode', false)
                ->where('dashboardScope', 'active_building'));
    }

    public function test_setting_all_buildings_persists_the_scope(): void
    {
        $this->actingAs($this->landlord)
            ->patch(route('dashboard.scope.update'), ['scope' => 'all_buildings'])
            ->assertRedirect();

        $layout = LandlordDashboard::query()
            ->where('landlord_id', $this->landlord->id)
            ->where('slug', DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->value('layout');
        $this->assertSame('all_buildings', $layout['scope']);

        // Phase-105: the bare landing is the portfolio overview...
        $this->actingAs($this->landlord)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->component('Portfolio/Home'));

        // ...and the all-buildings aggregate is the building dashboard via the 'all' sentinel.
        $this->actingAs($this->landlord)
            ->get(route('dashboard', ['building_id' => 'all']))
            ->assertInertia(fn ($page) => $page->where('allBuildingsMode', true));
    }

    public function test_query_param_overrides_persisted_scope(): void
    {
        $this->actingAs($this->landlord)->patch(route('dashboard.scope.update'), ['scope' => 'active_building']);

        $this->actingAs($this->landlord)
            ->get(route('dashboard', ['building_id' => 'all']))
            ->assertInertia(fn ($page) => $page->where('allBuildingsMode', true));
    }

    public function test_scope_rejects_an_invalid_value(): void
    {
        $this->actingAs($this->landlord)
            ->patch(route('dashboard.scope.update'), ['scope' => 'galaxy'])
            ->assertSessionHasErrors('scope');
    }

    public function test_setting_scope_preserves_widget_order(): void
    {
        $this->actingAs($this->landlord)->patch(route('dashboards.preferences.update'), [
            'widget_order' => ['recent-tickets', 'recent-payments', 'expiring-leases'],
        ]);

        $this->actingAs($this->landlord)->patch(route('dashboard.scope.update'), ['scope' => 'all_buildings']);

        $layout = LandlordDashboard::query()
            ->where('landlord_id', $this->landlord->id)
            ->where('slug', DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->value('layout');

        $this->assertSame('all_buildings', $layout['scope']);
        $this->assertSame(['recent-tickets', 'recent-payments', 'expiring-leases'], $layout['widgets']);
    }

    public function test_updating_widgets_preserves_scope(): void
    {
        $this->actingAs($this->landlord)->patch(route('dashboard.scope.update'), ['scope' => 'all_buildings']);
        $this->actingAs($this->landlord)->patch(route('dashboards.preferences.update'), [
            'widget_order' => ['expiring-leases'],
        ]);

        $layout = LandlordDashboard::query()
            ->where('landlord_id', $this->landlord->id)
            ->where('slug', DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->value('layout');

        $this->assertSame('all_buildings', $layout['scope']);
    }
}
