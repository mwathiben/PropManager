<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

#[Group('api')]
class LandlordApiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private array $setup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setup = $this->createLandlordWithFullSetup();
    }

    public function test_landlord_can_list_properties(): void
    {
        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/properties');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'type',
                        'buildings',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_can_view_single_property(): void
    {
        $landlord = $this->setup['landlord'];
        $property = $this->setup['property'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson("/api/v1/landlord/properties/{$property->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $property->id)
            ->assertJsonPath('data.name', $property->name);
    }

    public function test_landlord_can_list_buildings(): void
    {
        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/buildings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'total_floors',
                        'units_per_floor',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_can_view_building_units(): void
    {
        $landlord = $this->setup['landlord'];
        $building = $this->setup['building'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson("/api/v1/landlord/buildings/{$building->id}/units");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'unit_number',
                        'floor_number',
                        'status',
                        'target_rent',
                    ],
                ],
            ])
            ->assertJsonCount(8, 'data');
    }

    public function test_landlord_can_list_units(): void
    {
        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/units');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'unit_number',
                        'status',
                        'building',
                    ],
                ],
                'current_page',
                'total',
            ])
            ->assertJsonCount(8, 'data');
    }

    public function test_landlord_can_update_unit_status(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->patchJson("/api/v1/landlord/units/{$unit->id}/status", [
            'status' => 'maintenance',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('message', 'Status updated');

        $this->assertEquals('maintenance', $unit->fresh()->status);
    }

    public function test_landlord_can_list_invoices(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($landlord, $unit);
        $this->createInvoiceForLease($tenantData['lease'], 'sent');

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/invoices');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'total_due',
                        'status',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_can_list_payments(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($landlord, $unit);
        $lease = $tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 5000,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'PAY123',
        ]);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'payment_method',
                        'payment_date',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_can_get_occupancy_report(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        $this->createTenantWithActiveLease($landlord, $unit);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/reports/occupancy');

        $response->assertOk()
            ->assertJsonStructure([
                'total_units',
                'occupied',
                'vacant',
                'occupancy_rate',
            ]);
    }

    public function test_landlord_can_get_revenue_report(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($landlord, $unit);
        $lease = $tenantData['lease'];
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'REV123',
        ]);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/reports/revenue');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'total_revenue',
                'by_method',
                'transaction_count',
            ]);
    }

    public function test_landlord_can_get_arrears_report(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($landlord, $unit);
        $lease = $tenantData['lease'];

        Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-001',
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'overdue',
            'due_date' => now()->subDays(30),
            'billing_period_start' => now()->startOfMonth()->subMonth(),
        ]);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/reports/arrears');

        $response->assertOk()
            ->assertJsonStructure([
                'total_overdue',
                'invoice_count',
                'aged_receivables',
            ]);
    }

    public function test_caretaker_has_landlord_manage_access(): void
    {
        $landlord = $this->setup['landlord'];
        $building = $this->setup['building'];
        $caretaker = $this->createCaretakerForLandlord($landlord, $building);

        Sanctum::actingAs($caretaker, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/properties');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_cannot_access_other_landlords_data(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        $otherProperty = $otherSetup['property'];

        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson("/api/v1/landlord/properties/{$otherProperty->id}");

        $response->assertForbidden();
    }

    public function test_landlord_cannot_update_other_landlords_unit(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        $otherUnit = $otherSetup['units']->first();

        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->patchJson("/api/v1/landlord/units/{$otherUnit->id}/status", [
            'status' => 'maintenance',
        ]);

        $response->assertForbidden();
    }

    public function test_unit_status_validation(): void
    {
        $landlord = $this->setup['landlord'];
        $unit = $this->setup['units']->first();
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->patchJson("/api/v1/landlord/units/{$unit->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_pagination_works_for_units(): void
    {
        $landlord = $this->setup['landlord'];
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/units?per_page=3&page=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 3)
            ->assertJsonPath('current_page', 1)
            ->assertJsonCount(3, 'data');

        $response2 = $this->getJson('/api/v1/landlord/units?per_page=3&page=2');

        $response2->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(3, 'data');
    }
}
