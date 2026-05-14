<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuthAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-21 DEFER-AUTHZ-3: per-record abilities resolution via
 * AuthAbilities::forRecord(). Verifies the flat-map contract +
 * Policy outcome propagation for Invoice + Tenant Show payloads.
 */
class Phase21AuthzTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_record_returns_flat_boolean_map_with_requested_keys(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $invoice = $this->makeOwnedInvoice($landlord);

        $abilities = AuthAbilities::forRecord($landlord, $invoice, [
            'update', 'delete', 'recordPayment', 'send', 'pay', 'restore',
        ]);

        $this->assertSame(
            ['update', 'delete', 'recordPayment', 'send', 'pay', 'restore'],
            array_keys($abilities),
            'forRecord must emit exactly the requested keys in order.',
        );
        foreach ($abilities as $value) {
            $this->assertIsBool($value, 'Each ability value must be boolean.');
        }
    }

    public function test_for_record_respects_invoice_policy_owner_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $invoice = $this->makeOwnedInvoice($landlord);

        $abilities = AuthAbilities::forRecord($landlord, $invoice, [
            'update', 'delete', 'recordPayment', 'send', 'pay', 'restore',
        ]);

        $this->assertTrue($abilities['update'], 'Landlord owning the invoice can update.');
        $this->assertTrue($abilities['recordPayment'], 'Landlord owning the invoice can record payment.');
        $this->assertFalse($abilities['pay'], 'Pay ability is tenant-only.');
    }

    public function test_for_record_denies_cross_landlord_invoice(): void
    {
        $owner = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'landlord']);
        $invoice = $this->makeOwnedInvoice($owner);

        $abilities = AuthAbilities::forRecord($other, $invoice, ['update', 'delete']);

        $this->assertFalse($abilities['update'], 'Cross-landlord update must be denied.');
        $this->assertFalse($abilities['delete'], 'Cross-landlord delete must be denied.');
    }

    public function test_for_record_invoice_delete_respects_draft_status_only(): void
    {
        // InvoicePolicy::delete returns false for non-draft invoices even
        // for the owner — verify forRecord propagates that exact gate.
        $landlord = User::factory()->create(['role' => 'landlord']);
        $draft = $this->makeOwnedInvoice($landlord, 'draft');
        $sent = $this->makeOwnedInvoice($landlord, 'sent');

        $this->assertTrue(
            AuthAbilities::forRecord($landlord, $draft, ['delete'])['delete'],
            'Draft invoice — owner can delete.',
        );
        $this->assertFalse(
            AuthAbilities::forRecord($landlord, $sent, ['delete'])['delete'],
            'Sent invoice — even owner cannot delete (InvoicePolicy::delete draft-only).',
        );
    }

    public function test_for_record_tenant_policy_denies_create_and_delete_to_landlord(): void
    {
        // TenantPolicy::create + delete are explicitly false (Phase-19
        // POLICY-3 — tenant creation is invitation-only, deletion goes
        // via DPA workflow). forRecord must surface this contract.
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $abilities = AuthAbilities::forRecord($landlord, $tenant, [
            'view', 'viewLedger', 'update', 'delete', 'restore',
        ]);

        $this->assertTrue($abilities['view'], 'Landlord can view their tenant.');
        $this->assertTrue($abilities['viewLedger'], 'Landlord can view tenant ledger.');
        $this->assertTrue($abilities['update'], 'Landlord can update tenant profile.');
        $this->assertFalse($abilities['delete'], 'TenantPolicy::delete is explicit false (DPA workflow only).');
        $this->assertTrue($abilities['restore'], 'Landlord can restore soft-deleted own tenant.');
    }

    private function makeOwnedInvoice(User $landlord, string $status = 'draft'): Invoice
    {
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create([
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
        ]);
        $unit = Unit::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
        ]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        $lease = Lease::factory()->create([
            'landlord_id' => $landlord->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);

        return Invoice::factory()->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $lease->id,
            'status' => $status,
        ]);
    }
}
