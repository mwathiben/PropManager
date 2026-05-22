<?php

declare(strict_types=1);

namespace Tests\Feature\Hub;

use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-83 follow-up HUB-OVERVIEW: every tab-shell hub opens on an Overview
 * homepage (stat cards + quick links) instead of dumping the user into a
 * working first tab — matching the Finances hub.
 */
class Phase83HubOverviewTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    public static function hubRoutes(): array
    {
        return [
            'maintenance' => ['maintenance.hub'],
            'archive' => ['archive.hub'],
            'operations' => ['operations.hub'],
            'tenants' => ['tenants.hub'],
        ];
    }

    /**
     * @dataProvider hubRoutes
     */
    public function test_hub_opens_on_overview_with_stats(string $routeName): void
    {
        $response = $this->actingAs($this->landlord->fresh())->get(route($routeName));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('overview', $props['activeTab'], "{$routeName} should default to overview");
        $this->assertNotEmpty($props['overviewStats'], "{$routeName} overview should expose stat cards");
    }

    public function test_water_hub_opens_on_overview(): void
    {
        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $this->landlord->id],
            ['water_billing_type' => 'consumption', 'water_unit_rate' => 150],
        );
        WaterModuleAccess::forget($this->landlord->id);

        $response = $this->actingAs($this->landlord->fresh())->get(route('water.hub'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('overview', $props['activeTab']);
        $this->assertNotEmpty($props['overviewStats']);
    }

    public function test_hub_still_serves_a_named_working_tab(): void
    {
        // The overview is the default, but the working tabs still resolve.
        $this->actingAs($this->landlord->fresh())
            ->get(route('maintenance.hub', ['tab' => 'tickets']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeTab', 'tickets'));
    }
}
