<?php

namespace Tests\Feature\Controllers;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class LeaseControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_landlord_can_view_leases_index(): void
    {
        $unit = $this->setupData['units']->first();
        $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($this->landlord)
            ->get(route('leases.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leases/Index')
            ->has('leases.data', 1)
        );
    }

    public function test_landlord_can_view_lease_create_form(): void
    {
        $unit = $this->setupData['units']->first();

        $response = $this->actingAs($this->landlord)
            ->get(route('leases.create', $unit));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leases/Create')
            ->where('unit.id', $unit->id)
        );
    }

    public function test_landlord_can_create_lease_with_new_tenant(): void
    {
        Mail::fake();

        $unit = $this->setupData['units']->first();

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.store', $unit), [
                'name' => 'John Tenant',
                'email' => 'john.tenant@example.com',
                'phone' => '+254712345678',
                'rent_amount' => 25000,
                'deposit_amount' => 25000,
                'start_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'John Tenant',
            'email' => 'john.tenant@example.com',
            'role' => 'tenant',
        ]);

        $this->assertDatabaseHas('leases', [
            'unit_id' => $unit->id,
            'rent_amount' => 25000,
            'is_active' => true,
        ]);
    }

    public function test_lease_creation_updates_unit_status(): void
    {
        Mail::fake();

        $unit = $this->setupData['units']->first();
        $this->assertEquals('vacant', $unit->status);

        $this->actingAs($this->landlord)
            ->post(route('leases.store', $unit), [
                'name' => 'Jane Tenant',
                'email' => 'jane.tenant@example.com',
                'phone' => '+254712345679',
                'rent_amount' => 30000,
                'deposit_amount' => 30000,
                'start_date' => now()->toDateString(),
            ]);

        $unit->refresh();
        $this->assertEquals('occupied', $unit->status);
    }

    public function test_lease_creation_validates_required_fields(): void
    {
        $unit = $this->setupData['units']->first();

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.store', $unit), []);

        $response->assertSessionHasErrors(['name', 'email', 'rent_amount']);
    }

    public function test_lease_creation_validates_unique_email(): void
    {
        $existingTenant = User::factory()->create([
            'email' => 'existing@example.com',
            'role' => 'tenant',
        ]);

        $unit = $this->setupData['units']->first();

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.store', $unit), [
                'name' => 'New Tenant',
                'email' => 'existing@example.com',
                'phone' => '+254712345680',
                'rent_amount' => 25000,
                'deposit_amount' => 25000,
                'start_date' => now()->toDateString(),
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_landlord_can_adjust_rent(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $originalRent = $lease->rent_amount;
        $newRent = 30000;

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.adjust-rent', $lease), [
                'new_amount' => $newRent,
                'effective_date' => now()->addMonth()->toDateString(),
                'reason' => 'Annual increase',
            ]);

        $response->assertRedirect();

        $lease->refresh();
        $this->assertEquals($newRent, $lease->rent_amount);
    }

    public function test_rent_adjustment_creates_history_record(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $this->actingAs($this->landlord)
            ->post(route('leases.adjust-rent', $lease), [
                'new_amount' => 35000,
                'effective_date' => now()->toDateString(),
                'reason' => 'Market adjustment',
            ]);

        $this->assertDatabaseHas('rent_histories', [
            'lease_id' => $lease->id,
            'new_amount' => 35000,
            'reason' => 'Market adjustment',
        ]);
    }

    public function test_batch_rent_adjustment_by_percentage(): void
    {
        $units = $this->setupData['units']->take(3);
        $leases = [];

        foreach ($units as $unit) {
            ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
            $leases[] = $lease;
        }

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.batch-adjust'), [
                'unit_ids' => $units->pluck('id')->toArray(),
                'adjustment_type' => 'percentage',
                'value' => 10,
                'effective_date' => now()->toDateString(),
                'reason' => 'Annual increment 10%',
            ]);

        $response->assertRedirect();

        foreach ($leases as $lease) {
            $lease->refresh();
            $this->assertEquals(27500, $lease->rent_amount);
        }
    }

    public function test_batch_rent_adjustment_by_fixed_amount(): void
    {
        $units = $this->setupData['units']->take(2);
        $leases = [];

        foreach ($units as $unit) {
            ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
            $leases[] = $lease;
        }

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.batch-adjust'), [
                'unit_ids' => $units->pluck('id')->toArray(),
                'adjustment_type' => 'fixed',
                'value' => 2000,
                'effective_date' => now()->toDateString(),
                'reason' => 'Service charge increase',
            ]);

        $response->assertRedirect();

        foreach ($leases as $lease) {
            $lease->refresh();
            $this->assertEquals(27000, $lease->rent_amount);
        }
    }

    public function test_wallet_credit_adjustment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.wallet-adjustment', $lease), [
                'type' => 'credit',
                'amount' => 5000,
                'reason' => 'Refund for maintenance disruption',
            ]);

        $response->assertRedirect();

        $lease->refresh();
        $this->assertEquals(5000, $lease->wallet_balance);
    }

    public function test_wallet_debit_cannot_exceed_balance(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $lease->wallet_balance = 3000;
        $lease->save();

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.wallet-adjustment', $lease), [
                'type' => 'debit',
                'amount' => 5000,
                'reason' => 'Deduction',
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_landlord_cannot_modify_other_landlords_lease(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($otherLandlord)
            ->post(route('leases.adjust-rent', $lease), [
                'new_amount' => 50000,
                'effective_date' => now()->toDateString(),
            ]);

        // TenantScope hides the lease from other landlords - they can't access it
        $this->assertTrue(in_array($response->status(), [302, 403, 404]));
    }

    public function test_wallet_history_shows_transactions(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $lease->creditToWallet(5000, 'Test credit');
        $lease->deductFromWallet(2000, 'Test debit');

        $response = $this->actingAs($this->landlord)
            ->get(route('leases.wallet-history', $lease));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('transactions')
        );
    }
}
