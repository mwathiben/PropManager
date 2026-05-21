<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-78 PROPERTY-VIEW: property index + single-property show (owner-gated).
 */
class Phase78PropertyViewTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->property = $setup['property'];
    }

    public function test_index_renders_properties_with_metrics(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('properties.index'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertNotEmpty($props['properties']);
        $this->assertArrayHasKey('occupancy_pct', $props['properties'][0]);
    }

    public function test_show_renders_metrics_and_buildings_for_owner(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('properties.show', $this->property->id));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame($this->property->id, $props['property']['id']);
        $this->assertArrayHasKey('occupancy_pct', $props['metrics']);
        $this->assertNotEmpty($props['buildings']);
    }

    public function test_show_404_for_another_landlords_property(): void
    {
        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);

        $this->actingAs($other)
            ->get(route('properties.show', $this->property->id))
            ->assertNotFound();
    }
}
