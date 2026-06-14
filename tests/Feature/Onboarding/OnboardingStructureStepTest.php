<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterizes OnboardingService::processStructure() (the canonical step-4
 * path) so the BuildingStructureBuilder extraction preserves the single- and
 * winged-property building/unit shapes, the PaymentConfiguration base-rent
 * source, and the replace-on-rerun semantics. The legacy /onboarding/store path
 * is covered by OnboardingStoreLegacyTest; this guards the wizard path the
 * existing suite did not exercise end to end.
 */
class OnboardingStructureStepTest extends TestCase
{
    use RefreshDatabase;

    private function landlordWithProperty(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acacia Court',
            'type' => 'residential',
        ]);

        $this->actingAs($landlord);

        return [$landlord, $property];
    }

    private function processStructure(User $landlord, array $data): bool
    {
        return app(OnboardingService::class)->processStep(
            4,
            $data,
            $landlord,
            $landlord->getOrCreateOnboardingProgress(),
        );
    }

    public function test_single_building_generates_floor_by_units_grid(): void
    {
        [$landlord] = $this->landlordWithProperty();

        $result = $this->processStructure($landlord, [
            'has_wings' => false,
            'floors' => 2,
            'units_per_floor' => 3,
        ]);

        $this->assertTrue($result);
        $this->assertSame(1, Building::where('landlord_id', $landlord->id)->count());
        $this->assertSame(6, Unit::where('landlord_id', $landlord->id)->count());
        $this->assertEqualsWithDelta(
            10000.0,
            (float) Unit::where('landlord_id', $landlord->id)->value('target_rent'),
            0.001,
        );
    }

    public function test_winged_building_creates_container_plus_prefixed_wings(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord->id,
            'default_rent' => 8500,
        ]);

        $result = $this->processStructure($landlord, [
            'has_wings' => true,
            'wings' => [
                ['name' => 'Block A', 'prefix' => 'a', 'floors' => 1, 'units_per_floor' => 2],
                ['name' => 'Block B', 'prefix' => 'b', 'floors' => 1, 'units_per_floor' => 2],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertSame(3, Building::where('property_id', $property->id)->count());
        $this->assertSame(2, Building::where('property_id', $property->id)->where('is_wing', true)->count());
        $this->assertSame(4, Unit::where('landlord_id', $landlord->id)->count());
        $this->assertTrue(Unit::where('landlord_id', $landlord->id)->where('unit_number', 'A101')->exists());
        $this->assertTrue(Unit::where('landlord_id', $landlord->id)->where('unit_number', 'B102')->exists());
        $this->assertEqualsWithDelta(
            8500.0,
            (float) Unit::where('unit_number', 'A101')->value('target_rent'),
            0.001,
        );
    }

    public function test_rerunning_structure_replaces_existing_buildings_and_units(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();

        $this->processStructure($landlord, [
            'has_wings' => false,
            'floors' => 2,
            'units_per_floor' => 3,
        ]);

        $this->processStructure($landlord, [
            'has_wings' => true,
            'wings' => [
                ['name' => 'Block A', 'prefix' => 'A', 'floors' => 1, 'units_per_floor' => 2],
                ['name' => 'Block B', 'prefix' => 'B', 'floors' => 1, 'units_per_floor' => 2],
            ],
        ]);

        $this->assertSame(3, Building::where('property_id', $property->id)->count());
        $this->assertSame(4, Unit::where('landlord_id', $landlord->id)->count());
    }

    public function test_missing_property_returns_false_without_writing(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord);

        $result = $this->processStructure($landlord, [
            'has_wings' => false,
            'floors' => 2,
            'units_per_floor' => 3,
        ]);

        $this->assertFalse($result);
        $this->assertSame(0, Building::where('landlord_id', $landlord->id)->count());
    }
}
