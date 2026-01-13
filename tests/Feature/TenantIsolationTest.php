<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $landlordA;

    private User $landlordB;

    private Property $propertyA;

    private Property $propertyB;

    private Unit $unitA;

    private Unit $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlordA = User::factory()->create(['role' => 'landlord']);
        $this->landlordB = User::factory()->create(['role' => 'landlord']);

        $this->propertyA = Property::create([
            'name' => 'Property A',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlordA->id,
        ]);

        $this->propertyB = Property::create([
            'name' => 'Property B',
            'address' => '456 Other St',
            'type' => 'apartment',
            'landlord_id' => $this->landlordB->id,
        ]);

        $buildingA = Building::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Block A',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlordA->id,
        ]);

        $buildingB = Building::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Block B',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlordB->id,
        ]);

        $this->unitA = Unit::create([
            'building_id' => $buildingA->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlordA->id,
        ]);

        $this->unitB = Unit::create([
            'building_id' => $buildingB->id,
            'unit_number' => 'B101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 30000,
            'landlord_id' => $this->landlordB->id,
        ]);
    }

    public function test_landlord_cannot_access_other_landlords_units_via_api(): void
    {
        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/units/'.$this->unitB->id);

        $response->assertStatus(403);
    }

    public function test_landlord_cannot_access_other_landlords_buildings_via_api(): void
    {
        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/buildings/'.$this->unitB->building_id);

        $response->assertStatus(403);
    }

    public function test_units_index_only_returns_own_units(): void
    {
        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/units');

        $response->assertStatus(200);

        $unitIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($this->unitA->id, $unitIds);
        $this->assertNotContains($this->unitB->id, $unitIds);
    }

    public function test_landlord_cannot_update_other_landlords_unit(): void
    {
        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->patchJson('/api/v1/landlord/units/'.$this->unitB->id.'/status', [
            'status' => 'vacant',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('units', [
            'id' => $this->unitB->id,
            'status' => 'occupied',
        ]);
    }

    public function test_landlord_cannot_access_other_landlords_invoice(): void
    {
        $tenantB = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordB->id,
        ]);

        $leaseB = Lease::create([
            'unit_id' => $this->unitB->id,
            'tenant_id' => $tenantB->id,
            'rent_amount' => 30000,
            'deposit_amount' => 30000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlordB->id,
        ]);

        $invoiceB = Invoice::create([
            'lease_id' => $leaseB->id,
            'invoice_number' => 'INV-202601-0001',
            'rent_due' => 30000,
            'water_due' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlordB->id,
        ]);

        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/invoices/'.$invoiceB->id);

        $response->assertStatus(403);
    }

    public function test_landlord_cannot_access_other_landlords_payment(): void
    {
        $tenantB = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordB->id,
        ]);

        $leaseB = Lease::create([
            'unit_id' => $this->unitB->id,
            'tenant_id' => $tenantB->id,
            'rent_amount' => 30000,
            'deposit_amount' => 30000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlordB->id,
        ]);

        $invoiceB = Invoice::create([
            'lease_id' => $leaseB->id,
            'invoice_number' => 'INV-202601-0002',
            'rent_due' => 30000,
            'water_due' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlordB->id,
        ]);

        $paymentB = Payment::create([
            'invoice_id' => $invoiceB->id,
            'lease_id' => $leaseB->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'landlord_id' => $this->landlordB->id,
        ]);

        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/payments/'.$paymentB->id);

        $response->assertStatus(403);
    }

    public function test_caretaker_can_only_access_assigned_landlord_data(): void
    {
        $caretakerA = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlordA->id,
        ]);

        Sanctum::actingAs($caretakerA, ['landlord:manage']);

        $responseOwn = $this->getJson('/api/v1/landlord/units/'.$this->unitA->id);
        $responseOwn->assertStatus(200);

        $responseOther = $this->getJson('/api/v1/landlord/units/'.$this->unitB->id);
        $responseOther->assertStatus(403);
    }

    public function test_tenant_cannot_access_other_tenants_invoices(): void
    {
        $tenantA = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordA->id,
        ]);

        $tenantB = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordB->id,
        ]);

        $leaseA = Lease::create([
            'unit_id' => $this->unitA->id,
            'tenant_id' => $tenantA->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlordA->id,
        ]);

        $leaseB = Lease::create([
            'unit_id' => $this->unitB->id,
            'tenant_id' => $tenantB->id,
            'rent_amount' => 30000,
            'deposit_amount' => 30000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlordB->id,
        ]);

        $invoiceA = Invoice::create([
            'lease_id' => $leaseA->id,
            'invoice_number' => 'INV-202601-0003',
            'rent_due' => 25000,
            'water_due' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlordA->id,
        ]);

        $invoiceB = Invoice::create([
            'lease_id' => $leaseB->id,
            'invoice_number' => 'INV-202601-0004',
            'rent_due' => 30000,
            'water_due' => 0,
            'total_due' => 30000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlordB->id,
        ]);

        Sanctum::actingAs($tenantA, ['tenant:read']);

        $response = $this->getJson('/api/v1/tenant/invoices/'.$invoiceB->id);

        $response->assertStatus(403);
    }

    public function test_reports_only_include_own_data(): void
    {
        Sanctum::actingAs($this->landlordA, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/reports/occupancy');

        $response->assertStatus(200);
        $response->assertJsonPath('total_units', 1);
    }
}
