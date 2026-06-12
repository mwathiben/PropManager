<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Regression coverage for the Architect's bulk "Set Rent" / "Set Type"
 * action (POST buildings.update-units).
 *
 * The bug this guards: the Architect's rent field is a <input type="number">,
 * so the Inertia form posts `value` as a JSON number. UpdateUnitsRequest
 * validated `value` as a plain string, so every rent change failed
 * validation and silently no-op'd (302 redirect-back, modal stayed open,
 * rent never changed). The prior controller test sent `value: '30000'`
 * (a string) and so was a false green. These tests post the value the
 * real frontend sends.
 */
class BuildingUpdateUnitsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_set_rent_accepts_a_numeric_value_from_the_number_input(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithFullSetup();
        $unitIds = $building->units()->pluck('id')->all();

        $response = $this->actingAs($landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => $unitIds,
                'action' => 'update_rent',
                'value' => 32000, // a JS number, exactly as the type="number" input emits
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        foreach ($unitIds as $id) {
            $this->assertDatabaseHas('units', ['id' => $id, 'target_rent' => 32000]);
        }
    }

    public function test_set_rent_still_accepts_a_numeric_string(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithFullSetup();
        $unitId = $building->units()->value('id');

        $this->actingAs($landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => [$unitId],
                'action' => 'update_rent',
                'value' => '28500',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('units', ['id' => $unitId, 'target_rent' => 28500]);
    }

    public function test_set_rent_rejects_a_negative_value(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithFullSetup();
        $unitId = $building->units()->value('id');

        $this->actingAs($landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => [$unitId],
                'action' => 'update_rent',
                'value' => -100,
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_set_type_accepts_a_valid_unit_type_and_rejects_others(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithFullSetup();
        $unitId = $building->units()->value('id');

        $this->actingAs($landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => [$unitId],
                'action' => 'update_type',
                'value' => 'commercial',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unitId, 'unit_type' => 'commercial']);

        $this->actingAs($landlord)
            ->post(route('buildings.update-units', $building), [
                'selectedUnitIds' => [$unitId],
                'action' => 'update_type',
                'value' => 'spaceship',
            ])
            ->assertSessionHasErrors('value');
    }
}
