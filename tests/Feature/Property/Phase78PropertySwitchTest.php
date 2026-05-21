<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\Property\ActivePropertyResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-78 PROPERTY-SWITCH: persistent active property + owner-gated switch +
 * resolver + role gating (CodeRabbit H1/M3).
 */
class Phase78PropertySwitchTest extends TestCase
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

    public function test_switch_persists_the_active_property(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('properties.switch', $this->property->id))
            ->assertRedirect();

        $this->assertSame($this->property->id, $this->landlord->fresh()->active_property_id);
    }

    public function test_switch_rejects_another_landlords_property(): void
    {
        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);

        $this->actingAs($other)
            ->post(route('properties.switch', $this->property->id))
            ->assertNotFound();

        $this->assertNull($other->fresh()->active_property_id);
    }

    public function test_resolver_returns_stored_else_first_else_null(): void
    {
        $resolver = app(ActivePropertyResolver::class);

        // No stored → first property.
        $this->assertSame($this->property->id, $resolver->resolve($this->landlord)->id);

        // Stored + owned → returned.
        $this->landlord->active_property_id = $this->property->id;
        $this->landlord->save();
        $this->assertSame($this->property->id, $resolver->resolve($this->landlord->fresh())->id);

        // No properties → null.
        $lonely = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord']));
        $this->assertNull($resolver->resolve($lonely));
    }

    public function test_current_renders_the_active_property(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('properties.current'));
        $response->assertOk();
        $this->assertSame($this->property->id, $response->viewData('page')['props']['property']['id']);
    }

    public function test_caretaker_can_view_their_landlords_property(): void
    {
        $caretaker = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));

        $this->actingAs($caretaker)
            ->get(route('properties.show', $this->property->id))
            ->assertOk();
    }

    public function test_tenant_cannot_access_the_property_tier(): void
    {
        $tenant = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));

        $this->actingAs($tenant)->get(route('properties.index'))->assertForbidden();
    }

    public function test_caretaker_cannot_switch_the_active_property(): void
    {
        $caretaker = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));

        $this->actingAs($caretaker)
            ->post(route('properties.switch', $this->property->id))
            ->assertForbidden();

        $this->assertNull($caretaker->fresh()->active_property_id);
    }

    public function test_current_redirects_when_landlord_has_no_properties(): void
    {
        $lonely = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord']));

        $this->actingAs($lonely)
            ->get(route('properties.current'))
            ->assertRedirect(route('properties.index'));
    }
}
