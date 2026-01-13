<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_create_lease_and_onboard_tenant(): void
    {
        // Create a landlord
        $landlord = User::factory()->create([
            'role' => 'landlord',
        ]);

        // Create a property, building, and vacant unit for this landlord
        $property = \App\Models\Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = \App\Models\Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'floors' => 2,
            'units_per_floor' => 2,
            'landlord_id' => $landlord->id,
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 25000,
            'landlord_id' => $landlord->id,
        ]);

        // Act as landlord and submit lease creation form
        $response = $this->actingAs($landlord)->post(route('leases.store', $unit), [
            'name' => 'John Tenant',
            'email' => 'john.tenant@example.com',
            'phone' => '+254712345678',
            'id_number' => '12345678',
            'rent_amount' => 25000,
            'service_charge' => 500,
            'deposit_amount' => 25000,
            'start_date' => now()->format('Y-m-d'),
        ]);

        // Should redirect to dashboard on success
        $response->assertRedirect(route('dashboard'));

        // Verify tenant user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john.tenant@example.com',
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        // Verify lease was created
        $this->assertDatabaseHas('leases', [
            'unit_id' => $unit->id,
            'rent_amount' => 25000,
            'is_active' => true,
        ]);

        // Verify unit status changed to occupied
        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => 'occupied',
        ]);
    }

    public function test_lease_creation_validates_required_fields(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $property = \App\Models\Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = \App\Models\Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'floors' => 2,
            'units_per_floor' => 2,
            'landlord_id' => $landlord->id,
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 25000,
            'landlord_id' => $landlord->id,
        ]);

        // Submit with missing required fields
        $response = $this->actingAs($landlord)->post(route('leases.store', $unit), [
            'name' => '', // empty
            'email' => '', // empty
        ]);

        // Should return validation errors
        $response->assertSessionHasErrors(['name', 'email', 'phone', 'rent_amount', 'deposit_amount', 'start_date']);
    }

    public function test_lease_creation_validates_unique_email(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        // Create existing user with this email
        User::factory()->create(['email' => 'existing@example.com']);

        $property = \App\Models\Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = \App\Models\Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'floors' => 2,
            'units_per_floor' => 2,
            'landlord_id' => $landlord->id,
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 25000,
            'landlord_id' => $landlord->id,
        ]);

        // Try to create lease with existing email
        $response = $this->actingAs($landlord)->post(route('leases.store', $unit), [
            'name' => 'John Tenant',
            'email' => 'existing@example.com', // already exists
            'phone' => '+254712345678',
            'id_number' => '12345678',
            'rent_amount' => 25000,
            'service_charge' => 500,
            'deposit_amount' => 25000,
            'start_date' => now()->format('Y-m-d'),
        ]);

        // Should return validation error for email
        $response->assertSessionHasErrors(['email']);
    }
}
