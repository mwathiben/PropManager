<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\WaterModuleAccess;
use App\Services\WaterReadingService;
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

    // --- METER MODEL -----------------------------------------------------

    public function test_reading_uses_meter_non_zero_baseline_for_first_consumption(): void
    {
        $unit = $this->units->get(0);
        $meter = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'initial_reading' => 500,
            'status' => 'active',
        ]);

        $this->actingAs($this->landlord->fresh());
        $result = app(WaterReadingService::class)->processReading([
            'unit_id' => $unit->id,
            'current_reading' => 520,
            'reading_date' => now()->toDateString(),
        ], $this->landlord->id);

        $this->assertTrue($result['success']);
        $reading = WaterReading::where('meter_id', $meter->id)->firstOrFail();
        // Baseline 500, not 0 -> consumption is 20, not 520.
        $this->assertEquals(20, (float) $reading->consumption);
        $this->assertEquals(500, (float) $reading->previous_reading);
    }

    public function test_reading_for_unmetered_unit_lazily_creates_a_meter(): void
    {
        $unit = $this->units->get(1);
        $this->assertNull(Meter::where('unit_id', $unit->id)->first());

        $this->actingAs($this->landlord->fresh());
        $result = app(WaterReadingService::class)->processReading([
            'unit_id' => $unit->id,
            'current_reading' => 30,
            'reading_date' => now()->toDateString(),
        ], $this->landlord->id);

        $this->assertTrue($result['success']);
        $meter = Meter::where('unit_id', $unit->id)->first();
        $this->assertNotNull($meter);
        $this->assertSame($meter->id, WaterReading::where('unit_id', $unit->id)->firstOrFail()->meter_id);
    }

    public function test_below_baseline_reading_is_rejected(): void
    {
        $unit = $this->units->get(2);
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'initial_reading' => 500,
            'status' => 'active',
        ]);

        $this->actingAs($this->landlord->fresh());
        $result = app(WaterReadingService::class)->processReading([
            'unit_id' => $unit->id,
            'current_reading' => 400,
            'reading_date' => now()->toDateString(),
        ], $this->landlord->id);

        $this->assertFalse($result['success']);
        $this->assertSame(0, WaterReading::where('unit_id', $unit->id)->count());
    }

    public function test_meter_lifecycle_actions_are_landlord_only(): void
    {
        $caretaker = $this->caretaker();

        $this->assertTrue($this->landlord->fresh()->can('create', Meter::class));
        $this->assertFalse($caretaker->fresh()->can('create', Meter::class));
    }
}
