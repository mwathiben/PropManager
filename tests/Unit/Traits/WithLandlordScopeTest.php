<?php

namespace Tests\Unit\Traits;

use App\Models\Building;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithLandlordScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_access_finances_hub(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)->get(route('finances.overview'));

        $response->assertStatus(200);
    }

    public function test_caretaker_can_access_finances_hub(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $response = $this->actingAs($caretaker)->get(route('finances.overview'));

        $response->assertStatus(200);
    }

    public function test_tenant_cannot_access_finances_hub(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $response = $this->actingAs($tenant)->get(route('finances.overview'));

        $response->assertStatus(403);
    }

    public function test_landlord_only_sees_own_buildings(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $propertyA = Property::create([
            'name' => 'Property A',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlordA->id,
        ]);

        $propertyB = Property::create([
            'name' => 'Property B',
            'address' => '456 Other St',
            'type' => 'apartment',
            'landlord_id' => $landlordB->id,
        ]);

        Building::create([
            'property_id' => $propertyA->id,
            'name' => 'Landlord A Building',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $landlordA->id,
        ]);

        Building::create([
            'property_id' => $propertyB->id,
            'name' => 'Landlord B Building',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $landlordB->id,
        ]);

        $response = $this->actingAs($landlordA)->get(route('finances.overview'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('buildings', 1)
            ->where('buildings.0.name', 'Landlord A Building')
        );
    }

    public function test_caretaker_sees_landlord_buildings(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        Building::create([
            'property_id' => $property->id,
            'name' => 'Test Building',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $landlord->id,
        ]);

        $response = $this->actingAs($caretaker)->get(route('finances.overview'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('buildings', 1)
            ->where('buildings.0.name', 'Test Building')
        );
    }
}
