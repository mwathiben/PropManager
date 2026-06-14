<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Unit;
use App\Models\WaterConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The Architect's bulk unit-delete (BuildingService::bulkUpdateUnits('delete'),
 * exposed via buildings.update-units) must fail-closed when any selected unit
 * already carries an active lease, water readings, or a water connection —
 * otherwise a bulk action silently soft-deletes occupied/metered units,
 * orphaning tenancy + billing history.
 */
class BuildingBulkUnitDeleteGuardTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function postBulkDelete(\App\Models\User $landlord, \App\Models\Building $building, array $unitIds)
    {
        return $this->actingAs($landlord)->post(
            route('buildings.update-units', $building),
            ['selectedUnitIds' => $unitIds, 'action' => 'delete'],
        );
    }

    public function test_bulk_delete_is_blocked_when_a_selected_unit_has_an_active_lease(): void
    {
        ['landlord' => $landlord, 'building' => $building, 'units' => $units] = $this->createLandlordWithFullSetup();
        $leased = $units->first();
        $this->createTenantWithActiveLease($landlord, $leased);

        $this->postBulkDelete($landlord, $building, [$leased->id, $units->get(1)->id])
            ->assertSessionHasErrors('units');

        $this->assertNotSoftDeleted('units', ['id' => $leased->id]);
        $this->assertNotSoftDeleted('units', ['id' => $units->get(1)->id]);
    }

    public function test_bulk_delete_is_blocked_when_a_selected_unit_has_water_readings(): void
    {
        ['landlord' => $landlord, 'building' => $building, 'units' => $units] = $this->createLandlordWithFullSetup();
        $metered = $units->first();
        $this->createWaterReadingForUnit($metered);

        $this->postBulkDelete($landlord, $building, [$metered->id])
            ->assertSessionHasErrors('units');

        $this->assertNotSoftDeleted('units', ['id' => $metered->id]);
    }

    public function test_bulk_delete_is_blocked_when_a_selected_unit_has_a_water_connection(): void
    {
        ['landlord' => $landlord, 'building' => $building, 'units' => $units] = $this->createLandlordWithFullSetup();
        $connected = $units->first();
        WaterConnection::factory()->create([
            'landlord_id' => $landlord->id,
            'unit_id' => $connected->id,
        ]);

        $this->postBulkDelete($landlord, $building, [$connected->id])
            ->assertSessionHasErrors('units');

        $this->assertNotSoftDeleted('units', ['id' => $connected->id]);
    }

    public function test_bulk_delete_succeeds_for_units_without_dependents(): void
    {
        ['landlord' => $landlord, 'building' => $building, 'units' => $units] = $this->createLandlordWithFullSetup();
        $clean = [$units->get(1)->id, $units->get(2)->id];

        $this->postBulkDelete($landlord, $building, $clean)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSoftDeleted('units', ['id' => $units->get(1)->id]);
        $this->assertSoftDeleted('units', ['id' => $units->get(2)->id]);
    }

    public function test_bulk_delete_refuses_whole_batch_when_any_unit_has_a_dependency(): void
    {
        ['landlord' => $landlord, 'building' => $building, 'units' => $units] = $this->createLandlordWithFullSetup();
        $leased = $units->first();
        $clean = $units->get(1);
        $this->createTenantWithActiveLease($landlord, $leased);

        $this->postBulkDelete($landlord, $building, [$leased->id, $clean->id])
            ->assertSessionHasErrors('units');

        // All-or-nothing: the clean unit in the same batch must survive too.
        $this->assertNotSoftDeleted('units', ['id' => $leased->id]);
        $this->assertNotSoftDeleted('units', ['id' => $clean->id]);
    }
}
