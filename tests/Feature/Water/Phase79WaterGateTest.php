<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Building;
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
 * Phase-79 WATER-GATE: the water module is enabled only when the plan permits
 * AND the landlord actually charges for water.
 */
class Phase79WaterGateTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // WaterModuleAccess caches per landlord; ids reset under RefreshDatabase.
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
    }

    private function withWaterPlan(User $landlord): void
    {
        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create([
            'user_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    private function charge(string $type = 'consumption'): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => $type,
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    public function test_disabled_when_landlord_does_not_charge_for_water(): void
    {
        $this->withWaterPlan($this->landlord);
        // No PaymentConfiguration / no building water_billing_type.
        WaterModuleAccess::forget($this->landlord->id);

        $this->assertFalse(WaterModuleAccess::enabledFor($this->landlord->fresh()));
    }

    public function test_enabled_when_plan_permits_and_landlord_charges(): void
    {
        $this->withWaterPlan($this->landlord);
        $this->charge('consumption');

        $this->assertTrue(WaterModuleAccess::enabledFor($this->landlord->fresh()));
    }

    public function test_enabled_via_building_water_billing_type(): void
    {
        $this->withWaterPlan($this->landlord);
        $this->building->update(['water_billing_type' => 'flat_rate', 'water_flat_rate' => 500]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->assertTrue(WaterModuleAccess::enabledFor($this->landlord->fresh()));
    }

    public function test_disabled_when_plan_does_not_permit_even_if_charging(): void
    {
        // No water plan (free/none) but charging configured.
        $this->charge('consumption');

        $this->assertFalse(WaterModuleAccess::enabledFor($this->landlord->fresh()));
    }

    public function test_caretaker_inherits_the_landlord_gate(): void
    {
        $this->withWaterPlan($this->landlord);
        $this->charge('consumption');
        $caretaker = $this->createCaretakerForLandlord($this->landlord);

        $this->assertTrue(WaterModuleAccess::enabledFor($caretaker->fresh()));
    }

    public function test_water_hub_redirects_when_module_disabled(): void
    {
        $this->withWaterPlan($this->landlord);
        WaterModuleAccess::forget($this->landlord->id);

        $this->actingAs($this->landlord)
            ->get(route('water.hub'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_water_hub_accessible_when_module_enabled(): void
    {
        $this->withWaterPlan($this->landlord);
        $this->charge('consumption');

        $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub'))
            ->assertOk();
    }

    public function test_readings_store_blocked_when_module_disabled(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));
        WaterModuleAccess::forget($this->landlord->id);

        // Module disabled (no charge) → caretaker blocked at the gate.
        $this->actingAs($caretaker->fresh())
            ->post(route('readings.store'), [])
            ->assertRedirect(route('dashboard'));
    }
}
