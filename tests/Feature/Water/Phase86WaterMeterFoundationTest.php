<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-86 WATER-METER-FOUNDATION. This file covers the behavioural findings:
 * ROLE-SPLIT (caretaker never sees water billing Settings) plus the Meter
 * entity, baseline-aware consumption, replacement continuity and the spike flag
 * (added as those sub-phases land).
 */
class Phase86WaterMeterFoundationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    private function caretaker(): User
    {
        return Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));
    }

    // --- ROLE-SPLIT -------------------------------------------------------

    public function test_landlord_hub_can_see_settings(): void
    {
        $props = $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['canSettings']);
    }

    public function test_caretaker_hub_cannot_see_settings(): void
    {
        $props = $this->actingAs($this->caretaker()->fresh())
            ->get(route('water.hub'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($props['canSettings']);
    }

    public function test_caretaker_requesting_settings_tab_is_bounced_to_overview(): void
    {
        $props = $this->actingAs($this->caretaker()->fresh())
            ->get(route('water.hub', ['tab' => 'settings']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('overview', $props['activeTab']);
        // The landlord-only settings payload is never computed for a caretaker.
        $this->assertArrayNotHasKey('settings', $props);
    }

    public function test_caretaker_cannot_open_the_settings_page(): void
    {
        $this->actingAs($this->caretaker()->fresh())
            ->get(route('water.settings'))
            ->assertForbidden();
    }

    public function test_caretaker_cannot_update_water_settings(): void
    {
        $this->actingAs($this->caretaker()->fresh())
            ->put(route('water.settings.update'), ['water_billing_type' => 'consumption'])
            ->assertForbidden();
    }

    public function test_landlord_can_open_the_settings_page(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->get(route('water.settings'))
            ->assertOk();
    }
}
