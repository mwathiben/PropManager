<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DepositManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private Building $building;

    private Unit $unit;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->tenant = User::factory()->create(['role' => 'tenant', 'email' => 'tenant@example.com']);

        $this->property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->building = Building::create([
            'property_id' => $this->property->id,
            'name' => 'Block A',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'target_rent' => 15000,
            'status' => 'occupied',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now(),
            'rent_amount' => 15000,
            'deposit_amount' => 30000,
            'deposit_status' => 'held',
            'is_active' => true,
        ]);

        Mail::fake();
    }

    public function test_can_refund_full_deposit(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 30000,
            'deductions' => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->lease->refresh();
        $this->assertEquals('refunded', $this->lease->deposit_status);
        $this->assertEquals(30000, $this->lease->deposit_refund_amount);
        $this->assertEquals(0, $this->lease->deposit_deductions);

        $this->assertDatabaseHas('deposit_transactions', [
            'lease_id' => $this->lease->id,
            'type' => 'full_refund',
            'amount' => 30000,
            'balance_after' => 0,
        ]);
    }

    public function test_can_refund_partial_deposit_with_deductions(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 20000,
            'deductions' => 10000,
            'deduction_reason' => 'Repair damages',
        ]);

        $response->assertRedirect();

        $this->lease->refresh();
        $this->assertEquals('partial_refund', $this->lease->deposit_status);
        $this->assertEquals(20000, $this->lease->deposit_refund_amount);
        $this->assertEquals(10000, $this->lease->deposit_deductions);
        $this->assertEquals('Repair damages', $this->lease->deposit_deduction_reason);

        $this->assertDatabaseHas('deposit_transactions', [
            'lease_id' => $this->lease->id,
            'type' => 'deduction',
            'amount' => 10000,
        ]);

        $this->assertDatabaseHas('deposit_transactions', [
            'lease_id' => $this->lease->id,
            'type' => 'partial_refund',
            'amount' => 20000,
        ]);
    }

    public function test_can_forfeit_deposit(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.deposits.forfeit', $this->lease), [
            'reason' => 'Breach of contract',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->lease->refresh();
        $this->assertEquals('forfeited', $this->lease->deposit_status);
        $this->assertEquals(30000, $this->lease->deposit_deductions);
        $this->assertEquals('Breach of contract', $this->lease->deposit_deduction_reason);

        $this->assertDatabaseHas('deposit_transactions', [
            'lease_id' => $this->lease->id,
            'type' => 'forfeit',
            'amount' => 30000,
            'balance_after' => 0,
        ]);
    }

    public function test_cannot_process_already_processed_deposit(): void
    {
        $this->actingAs($this->landlord);

        $this->lease->update(['deposit_status' => 'refunded']);

        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 30000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    public function test_refund_validation_prevents_overpayment(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 35000,
        ]);

        $response->assertSessionHasErrors('refund_amount');
    }

    public function test_forfeit_requires_reason(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.deposits.forfeit', $this->lease), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_deposit_transactions_endpoint_returns_history(): void
    {
        $this->actingAs($this->landlord);

        DepositTransaction::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'processed_by' => $this->landlord->id,
            'type' => 'received',
            'amount' => 30000,
            'balance_after' => 30000,
            'reason' => 'Initial deposit',
        ]);

        $response = $this->get(route('finances.deposits.transactions', $this->lease));

        $response->assertOk();
        $response->assertJsonStructure([
            'transactions' => [
                '*' => ['id', 'type', 'type_label', 'amount', 'balance_after'],
            ],
            'deposit_amount',
            'deposit_status',
        ]);
    }

    public function test_deposit_export_works(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->get(route('finances.deposits.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_deposit_export_pdf_works(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->get(route('finances.deposits.export', ['format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_tenant_isolation_for_deposits(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($otherLandlord);

        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 30000,
        ]);

        $response->assertForbidden();
    }

    public function test_deposit_requires_authentication(): void
    {
        $response = $this->post(route('finances.deposits.refund', $this->lease), [
            'refund_amount' => 30000,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_deposits_list_includes_transaction_history(): void
    {
        $this->actingAs($this->landlord);

        DepositTransaction::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'processed_by' => $this->landlord->id,
            'type' => 'received',
            'amount' => 30000,
            'balance_after' => 30000,
            'reason' => 'Initial deposit',
        ]);

        $response = $this->get(route('finances.deposits'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finances/Index')
            ->has('deposits.data.0.transactions')
        );
    }
}
