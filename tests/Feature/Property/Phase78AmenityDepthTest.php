<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Models\Building;
use App\Models\BuildingAmenityDetail;
use App\Models\User;
use App\Services\Building\AmenityDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-78 AMENITY-DEPTH: per-amenity detail sync (allow-list + selection gated)
 * + getActiveAmenities merge + the settings route.
 */
class Phase78AmenityDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->actingAs($this->landlord);
    }

    private function select(array $keys): void
    {
        $this->building->update(['amenities' => ['selected' => $keys, 'custom' => []]]);
    }

    public function test_sync_persists_details_for_selected_amenities(): void
    {
        $this->select(['parking', 'wifi']);

        app(AmenityDetailService::class)->sync($this->building, [
            'parking' => ['quantity' => 10],
            'wifi' => ['provider' => 'Zuku', 'monthly_cost_cents' => 500000],
        ]);

        $this->assertSame(2, BuildingAmenityDetail::where('building_id', $this->building->id)->count());
        $this->assertSame(10, BuildingAmenityDetail::where('amenity_key', 'parking')->first()->quantity);
        $this->assertSame('Zuku', BuildingAmenityDetail::where('amenity_key', 'wifi')->first()->provider);
    }

    public function test_sync_rejects_unknown_or_unselected_keys(): void
    {
        $this->select(['parking']);

        app(AmenityDetailService::class)->sync($this->building, [
            'parking' => ['quantity' => 5],
            'wifi' => ['provider' => 'X'],        // not selected
            'bogus_key' => ['quantity' => 1],     // not a real amenity
        ]);

        $keys = BuildingAmenityDetail::where('building_id', $this->building->id)->pluck('amenity_key')->all();
        $this->assertSame(['parking'], $keys);
    }

    public function test_sync_prunes_deselected_amenities(): void
    {
        $this->select(['parking']);
        app(AmenityDetailService::class)->sync($this->building, ['parking' => ['quantity' => 5]]);
        $this->assertSame(1, BuildingAmenityDetail::where('building_id', $this->building->id)->count());

        $this->select(['wifi']);
        app(AmenityDetailService::class)->sync($this->building->refresh(), ['wifi' => ['provider' => 'Faiba']]);

        $this->assertNull(BuildingAmenityDetail::where('amenity_key', 'parking')->first());
        $this->assertNotNull(BuildingAmenityDetail::where('amenity_key', 'wifi')->first());
    }

    public function test_get_active_amenities_includes_detail(): void
    {
        $this->select(['parking']);
        app(AmenityDetailService::class)->sync($this->building, ['parking' => ['quantity' => 12]]);

        $active = $this->building->fresh()->load('amenityDetails')->getActiveAmenities();
        $parking = collect($active)->firstWhere('key', 'parking');

        $this->assertNotNull($parking['detail']);
        $this->assertSame(12, $parking['detail']['quantity']);
    }

    public function test_update_settings_route_persists_amenity_details(): void
    {
        $this->put(route('buildings.update-settings', $this->building->id), [
            'name' => $this->building->name,
            'building_type' => 'residential_apartment',
            'amenities' => ['selected' => ['parking'], 'custom' => []],
            'amenity_details' => ['parking' => ['quantity' => 8]],
        ])->assertRedirect();

        $this->assertSame(8, BuildingAmenityDetail::where('building_id', $this->building->id)->where('amenity_key', 'parking')->first()->quantity);
    }
}
