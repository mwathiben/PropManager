<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRIV-12: TenantPolicy gates the 6 TenantController ledger / modal /
 * outstanding / refundable methods. The inline auth previously
 * duplicated across each method is now centralised; these tests lock
 * in the same-landlord-only invariant via the policy entry-points.
 */
class TenantPolicyLedgerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $landlordA;

    private User $caretakerA;

    private User $landlordB;

    private User $tenantA;

    private User $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlordA = User::factory()->create(['role' => 'landlord']);
        $this->landlordB = User::factory()->create(['role' => 'landlord']);

        $this->caretakerA = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlordA->id,
        ]);

        $this->tenantA = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordA->id,
        ]);
        $this->tenantB = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlordB->id,
        ]);

        // Lease needed only so the ledger render path doesn't choke on
        // missing rentHistory. Minimal scaffold.
        $property = Property::factory()->create(['landlord_id' => $this->landlordA->id]);
        $unit = Unit::factory()->create(['landlord_id' => $this->landlordA->id]);
        Lease::factory()->create([
            'landlord_id' => $this->landlordA->id,
            'tenant_id' => $this->tenantA->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_landlord_can_view_own_tenants_ledger(): void
    {
        $this->actingAs($this->landlordA)
            ->get("/tenants/{$this->tenantA->id}/ledger")
            ->assertStatus(200);
    }

    public function test_caretaker_can_view_landlords_tenant_ledger(): void
    {
        $this->actingAs($this->caretakerA)
            ->get("/tenants/{$this->tenantA->id}/ledger")
            ->assertStatus(200);
    }

    public function test_other_landlord_cannot_view_tenant_ledger(): void
    {
        $this->actingAs($this->landlordB)
            ->get("/tenants/{$this->tenantA->id}/ledger")
            ->assertStatus(403);
    }

    public function test_tenant_role_cannot_view_other_tenants_ledger(): void
    {
        $this->actingAs($this->tenantB)
            ->get("/tenants/{$this->tenantA->id}/ledger")
            ->assertStatus(403);
    }

    public function test_outstanding_invoices_returns_403_with_stable_shape_when_denied(): void
    {
        $this->actingAs($this->landlordB)
            ->getJson("/tenants/{$this->tenantA->id}/outstanding-invoices")
            ->assertStatus(403)
            ->assertJson(['data' => []]);
    }

    public function test_refundable_payments_returns_403_with_stable_shape_when_denied(): void
    {
        $this->actingAs($this->landlordB)
            ->getJson("/tenants/{$this->tenantA->id}/refundable-payments")
            ->assertStatus(403)
            ->assertJson(['data' => []]);
    }

    public function test_modal_data_denied_for_other_landlord(): void
    {
        $this->actingAs($this->landlordB)
            ->get("/tenants/{$this->tenantA->id}/modal-data")
            ->assertStatus(403);
    }
}
