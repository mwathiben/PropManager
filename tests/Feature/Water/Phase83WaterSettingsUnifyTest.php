<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Water\WaterModuleAccess;
use App\Services\Water\WaterSettingsData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-83 follow-up WATER-SETTINGS-UNIFY: the hub Settings tab, the standalone
 * /water/settings page, and the per-building page must all be ONE canonical
 * editor over the model WaterRateService actually bills from
 * (PaymentConfiguration + Building overrides). Previously the hub tab edited an
 * orphan WaterSetting model and 422'd on save.
 */
class Phase83WaterSettingsUnifyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
    }

    private function enableWaterModule(): void
    {
        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $this->landlord->id],
            ['water_billing_type' => 'consumption', 'water_unit_rate' => 150],
        );
        WaterModuleAccess::forget($this->landlord->id);
    }

    public function test_canonical_data_builder_returns_global_and_overrides(): void
    {
        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $this->landlord->id],
            ['water_billing_type' => 'flat_rate', 'flat_water_rate' => 500],
        );

        $data = WaterSettingsData::forLandlord($this->landlord->id);

        $this->assertSame('flat_rate', $data['globalSettings']['water_billing_type']);
        $this->assertSame(500.0, $data['globalSettings']['flat_water_rate']);
        $this->assertTrue($data['buildings']->contains('id', $this->building->id));
    }

    public function test_hub_settings_tab_serves_canonical_shape(): void
    {
        $this->enableWaterModule();

        $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub', ['tab' => 'settings']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Water/Hub')
                ->where('activeTab', 'settings')
                ->has('settings.buildings')
                ->has('settings.globalSettings.water_billing_type')
            );
    }

    public function test_unified_save_persists_global_and_building_override(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('water.settings.update'), [
                'water_billing_type' => 'consumption',
                'water_unit_rate' => 175,
                'flat_water_rate' => 0,
                'building_overrides' => [
                    ['id' => $this->building->id, 'water_billing_type' => 'flat_rate', 'water_flat_rate' => 900],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_configurations', [
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 175,
        ]);
        $this->building->refresh();
        $this->assertSame('flat_rate', $this->building->water_billing_type);
        $this->assertSame('900.00', (string) $this->building->water_flat_rate);
    }

    public function test_building_water_settings_redirects_to_unified_editor(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('buildings.water-settings', $this->building->id))
            ->assertRedirect(route('water.settings', ['building' => $this->building->id]));
    }
}
