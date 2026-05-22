<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\MeterReplacementService;
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

    // --- METER LIFECYCLE -------------------------------------------------

    public function test_replacement_preserves_continuity_via_new_baseline(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(0);

        // withoutEvents: WaterReadingFactory::definition() spins up a throwaway
        // Unit whose UnitObserver trips OnboardingMilestoneRecorder under actingAs.
        $meter = Model::withoutEvents(function () use ($unit) {
            $m = Meter::factory()->create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $unit->building_id,
                'unit_id' => $unit->id,
                'initial_reading' => 100,
                'status' => 'active',
            ]);
            WaterReading::factory()->forMeter($m)->create([
                'previous_reading' => 100,
                'current_reading' => 150,
                'reading_date' => now()->subMonth()->toDateString(),
                'status' => 'approved',
            ]);

            return $m;
        });

        $new = app(MeterReplacementService::class)->replace($meter->fresh(), 170, 'NEW-123', 5);

        $this->assertSame('replaced', $meter->fresh()->status->value);
        $this->assertSame($new->id, $meter->fresh()->replaced_by_meter_id);
        $this->assertSame('active', $new->status->value);
        $this->assertEquals(5, (float) $new->initial_reading);
        // The old meter's closing read (170) is captured.
        $this->assertTrue(WaterReading::where('meter_id', $meter->id)->where('current_reading', 170)->exists());

        // A fresh reading now routes to the NEW meter, measured from its baseline.
        $result = app(WaterReadingService::class)->processReading([
            'unit_id' => $unit->id,
            'current_reading' => 12,
            'reading_date' => now()->addDay()->toDateString(),
        ], $this->landlord->id);
        $this->assertTrue($result['success']);
        $this->assertEquals(7, (float) WaterReading::where('meter_id', $new->id)->firstOrFail()->consumption);
    }

    public function test_landlord_can_register_a_meter_with_non_zero_baseline(): void
    {
        $unit = $this->units->get(3);

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.store'), [
                'building_id' => $unit->building_id,
                'unit_id' => $unit->id,
                'serial_number' => 'WM-555',
                'initial_reading' => 320,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('water_meters', [
            'serial_number' => 'WM-555',
            'initial_reading' => 320,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_meters_index_and_store_are_landlord_only(): void
    {
        $caretaker = $this->caretaker()->fresh();

        $this->actingAs($caretaker)->get(route('meters.index'))->assertForbidden();
        $this->actingAs($caretaker)->post(route('meters.store'), ['initial_reading' => 0])->assertForbidden();
        $this->actingAs($this->landlord->fresh())->get(route('meters.index'))->assertOk();
    }

    // --- REVIEW HARDENING ------------------------------------------------

    public function test_reading_for_another_landlords_unit_is_rejected(): void
    {
        $otherUnit = $this->createLandlordWithFullSetup()['units']->get(0);

        $this->actingAs($this->landlord->fresh());
        $result = app(WaterReadingService::class)->processReading([
            'unit_id' => $otherUnit->id,
            'current_reading' => 50,
            'reading_date' => now()->toDateString(),
        ], $this->landlord->id);

        $this->assertFalse($result['success']);
        $this->assertSame(0, WaterReading::where('unit_id', $otherUnit->id)->count());
        // The cross-tenant meter must NOT have been lazily created.
        $this->assertSame(0, Meter::withoutGlobalScopes()->where('unit_id', $otherUnit->id)->count());
    }

    public function test_store_rejects_another_landlords_building(): void
    {
        $otherBuilding = $this->createLandlordWithFullSetup()['building'];

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.store'), [
                'building_id' => $otherBuilding->id,
                'initial_reading' => 10,
            ])
            ->assertSessionHasErrors('building_id');

        $this->assertDatabaseMissing('water_meters', ['building_id' => $otherBuilding->id]);
    }

    public function test_decommissioning_a_replaced_meter_is_rejected(): void
    {
        $unit = $this->units->get(4);
        $meter = Meter::factory()->replaced()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(MeterReplacementService::class)->decommission($meter);
    }

    public function test_cannot_register_a_second_active_meter_for_a_unit(): void
    {
        $unit = $this->units->get(5);
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.store'), ['unit_id' => $unit->id, 'initial_reading' => 10])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_replace_below_baseline_returns_form_error_not_500(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(6);

        $meter = Model::withoutEvents(function () use ($unit) {
            $m = Meter::factory()->create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $unit->building_id,
                'unit_id' => $unit->id,
                'initial_reading' => 100,
                'status' => 'active',
            ]);
            WaterReading::factory()->forMeter($m)->create([
                'previous_reading' => 100,
                'current_reading' => 150,
                'reading_date' => now()->subMonth()->toDateString(),
                'status' => 'approved',
            ]);

            return $m;
        });

        $this->post(route('meters.replace', $meter->id), [
            'old_final_reading' => 80,
            'new_initial_reading' => 0,
        ])->assertSessionHasErrors('old_final_reading');

        $this->assertSame('active', $meter->fresh()->status->value);
    }

    // --- READING INTEGRITY (spike flag) ----------------------------------

    public function test_spike_reading_is_flagged_normal_is_not(): void
    {
        $this->actingAs($this->landlord->fresh());

        $build = function ($unit) {
            return Model::withoutEvents(function () use ($unit) {
                $meter = Meter::factory()->create([
                    'landlord_id' => $this->landlord->id,
                    'building_id' => $unit->building_id,
                    'unit_id' => $unit->id,
                    'initial_reading' => 0,
                    'status' => 'active',
                ]);
                $prev = 0;
                $day = 40;
                foreach ([10, 20, 30] as $cur) {
                    WaterReading::factory()->forMeter($meter)->create([
                        'previous_reading' => $prev,
                        'current_reading' => $cur,
                        'consumption' => $cur - $prev,
                        'status' => 'approved',
                        'reading_date' => now()->subDays($day)->toDateString(),
                    ]);
                    $prev = $cur;
                    $day -= 5;
                }

                return $meter;
            });
        };

        $spikeUnit = $this->units->get(7);
        $normalUnit = $this->units->get(0);
        $spikeMeter = $build($spikeUnit);
        $normalMeter = $build($normalUnit);

        $svc = app(WaterReadingService::class);
        // Trailing average consumption is 10. spike: 90-30=60 (> 5x); normal: 38-30=8.
        $svc->processReading(['unit_id' => $spikeUnit->id, 'current_reading' => 90, 'reading_date' => now()->toDateString()], $this->landlord->id);
        $svc->processReading(['unit_id' => $normalUnit->id, 'current_reading' => 38, 'reading_date' => now()->toDateString()], $this->landlord->id);

        $this->assertTrue((bool) WaterReading::where('meter_id', $spikeMeter->id)->where('current_reading', 90)->firstOrFail()->is_anomalous);
        $this->assertFalse((bool) WaterReading::where('meter_id', $normalMeter->id)->where('current_reading', 38)->firstOrFail()->is_anomalous);
    }
}
