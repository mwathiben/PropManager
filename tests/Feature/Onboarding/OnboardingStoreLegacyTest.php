<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: OnboardingService::storeLegacy() discarded the Property::create()
 * result while both structure branches referenced $property->id, raising an
 * "Attempt to read property id on null" fatal that 500'd the legacy
 * /onboarding/store endpoint. Capturing the created row fixes both paths.
 */
class OnboardingStoreLegacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_store_creates_a_single_building_property(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.store'), [
                'propertyName' => 'Acacia Court',
                'propertyType' => 'residential',
                'hasWings' => false,
                'floors' => 2,
                'unitsPerFloor' => 3,
                'baseRent' => 15000,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('properties', [
            'landlord_id' => $landlord->id,
            'name' => 'Acacia Court',
        ]);
        $this->assertSame(1, Building::where('landlord_id', $landlord->id)->count());
        $this->assertSame(6, Unit::where('landlord_id', $landlord->id)->count());
    }

    public function test_legacy_store_creates_a_winged_property(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.store'), [
                'propertyName' => 'Riverside Estate',
                'propertyType' => 'estate',
                'hasWings' => true,
                'baseRent' => 20000,
                'wings' => [
                    ['name' => 'Block A', 'prefix' => 'A', 'floors' => 1, 'unitsPerFloor' => 2],
                    ['name' => 'Block B', 'prefix' => 'B', 'floors' => 1, 'unitsPerFloor' => 2],
                ],
            ])
            ->assertRedirect(route('dashboard'));

        $property = Property::where('landlord_id', $landlord->id)->firstOrFail();

        // One parent building + two wings.
        $this->assertSame(3, Building::where('property_id', $property->id)->count());
        $this->assertSame(2, Building::where('property_id', $property->id)->where('is_wing', true)->count());
        // Two units per wing, none on the parent.
        $this->assertSame(4, Unit::where('landlord_id', $landlord->id)->count());
    }
}
