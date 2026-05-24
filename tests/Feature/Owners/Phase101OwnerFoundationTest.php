<?php

declare(strict_types=1);

namespace Tests\Feature\Owners;

use App\Mail\OwnerStatementMail;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Services\OwnerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-101 OWNER-FOUNDATION: a property owner as a first-class contact entity (no login
 * role), property assignment, and a consolidated owner statement (download + email).
 */
class Phase101OwnerFoundationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    // --- CRUD + SCOPING --------------------------------------------------

    public function test_landlord_creates_updates_and_deletes_an_owner(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        $this->actingAs($landlord)
            ->post(route('finances.owners.store'), ['name' => 'Acme Holdings', 'email' => 'acme@example.com'])
            ->assertRedirect();

        $owner = PropertyOwner::where('landlord_id', $landlord->id)->firstWhere('name', 'Acme Holdings');
        $this->assertNotNull($owner);
        $this->assertSame('acme@example.com', $owner->email);

        $this->actingAs($landlord)
            ->put(route('finances.owners.update', $owner->id), ['name' => 'Acme Ltd', 'email' => 'acme@example.com', 'is_active' => true])
            ->assertRedirect();
        $this->assertSame('Acme Ltd', $owner->fresh()->name);

        $this->actingAs($landlord)->delete(route('finances.owners.destroy', $owner->id))->assertRedirect();
        $this->assertOwnerDeleted($owner);
    }

    public function test_owner_is_scoped_to_the_landlord(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        $foreignOwner = PropertyOwner::factory()->forLandlord($other['landlord'])->create();

        // Another landlord cannot reach someone else's owner. Denied as 404 (tenant-scoped
        // route bind) or 403 (policy) depending on model boot order — both secure; the
        // codebase treats owner-gating as 403-or-404 nondeterministic. Either way, no mutation.
        $status = $this->actingAs($setup['landlord'])
            ->put(route('finances.owners.update', $foreignOwner->id), ['name' => 'Hijack'])
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertNotSame('Hijack', $foreignOwner->fresh()->name);
    }

    public function test_owners_index_forbidden_for_a_tenant(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $this->actingAs($tenant->fresh())->get(route('finances.owners.index'))->assertForbidden();
    }

    // --- ASSIGNMENT ------------------------------------------------------

    public function test_assign_and_unassign_a_property(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $owner = PropertyOwner::factory()->forLandlord($landlord)->create();

        $this->actingAs($landlord)
            ->put(route('properties.owner.assign', ['property' => $setup['property']->id, 'owner' => $owner->id]))
            ->assertRedirect();
        $this->assertSame($owner->id, $setup['property']->fresh()->property_owner_id);

        $this->actingAs($landlord)
            ->delete(route('properties.owner.unassign', $setup['property']->id))
            ->assertRedirect();
        $this->assertNull($setup['property']->fresh()->property_owner_id);
    }

    public function test_cannot_assign_a_foreign_owner_to_my_property(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        $foreignOwner = PropertyOwner::factory()->forLandlord($other['landlord'])->create();

        $status = $this->actingAs($setup['landlord'])
            ->put(route('properties.owner.assign', ['property' => $setup['property']->id, 'owner' => $foreignOwner->id]))
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertNull($setup['property']->fresh()->property_owner_id); // no cross-tenant link
    }

    public function test_caretaker_can_view_owners_and_a_statement(): void
    {
        // Consistent with Phase-100: caretakers (PM staff) can access the finances reports
        // surface, including owner statements. Mutations stay landlord-only (policy).
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        $caretaker = \App\Models\User::factory()->create(['role' => 'caretaker', 'landlord_id' => $setup['landlord']->id]);

        $this->actingAs($caretaker->fresh())->get(route('finances.owners.index'))->assertOk();
        $this->actingAs($caretaker->fresh())
            ->get(route('finances.owners.statement', ['owner' => $owner->id, 'period' => '12']))
            ->assertOk();
    }

    // --- STATEMENT (forOwner aggregation) --------------------------------

    public function test_statement_only_aggregates_the_owners_properties_and_excludes_voided(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        // Revenue + a voided payment + an expense on the setup property.
        $this->createPaymentWithInvoice($lease, 30000);
        ['payment' => $voided] = $this->createPaymentWithInvoice($lease, 4000);
        $voided->forceFill(['is_voided' => true, 'voided_at' => now()])->save();
        \App\Models\Expense::create([
            'landlord_id' => $landlord->id, 'building_id' => $setup['building']->id,
            'description' => 'Repairs', 'amount' => 5000, 'expense_date' => now(),
        ]);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create();
        $svc = app(OwnerStatementService::class);
        $start = Carbon::now()->subMonth();
        $end = Carbon::now()->addDay();

        // Before assignment: the owner holds no property → sees nothing.
        $empty = $svc->forOwner($landlord->id, $owner->id, $start, $end);
        $this->assertEqualsWithDelta(0.0, $empty['collected'], 0.01);
        $this->assertCount(0, $empty['properties']);

        // After assignment: the property's collected (excl. voided) + expenses roll up.
        $setup['property']->update(['property_owner_id' => $owner->id]);
        $data = $svc->forOwner($landlord->id, $owner->id, $start, $end);

        $this->assertEqualsWithDelta(30000.0, $data['collected'], 0.01); // voided 4000 excluded
        $this->assertEqualsWithDelta(5000.0, $data['total_expenses'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $data['net'], 0.01);
        $this->assertCount(1, $data['properties']);
        $this->assertEqualsWithDelta(25000.0, $data['properties'][0]['net'], 0.01);
    }

    public function test_statement_pdf_endpoint_and_foreign_owner_404(): void
    {
        // Build BOTH landlords' data before acting as anyone — creating a second
        // landlord's property while authed as the first mis-stamps observer milestones.
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        $other = $this->createLandlordWithFullSetup();
        $foreign = PropertyOwner::factory()->forLandlord($other['landlord'])->create();

        $pdf = $this->actingAs($setup['landlord'])->get(route('finances.owners.statement', ['owner' => $owner->id, 'period' => '12']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        // Foreign owner denied — 404 (scoped bind) or 403 (policy), both secure.
        $foreignStatus = $this->actingAs($setup['landlord'])
            ->get(route('finances.owners.statement', ['owner' => $foreign->id]))
            ->getStatusCode();
        $this->assertContains($foreignStatus, [403, 404]);
    }

    public function test_email_statement_queues_mail_to_the_owner(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create(['email' => 'owner@example.com']);

        $this->actingAs($setup['landlord'])
            ->post(route('finances.owners.statement.email', $owner->id), ['period' => '12'])
            ->assertRedirect();

        Mail::assertQueued(OwnerStatementMail::class, fn (OwnerStatementMail $m) => $m->hasTo('owner@example.com'));
    }

    public function test_email_statement_without_an_email_does_not_queue(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create(['email' => null]);

        $this->actingAs($setup['landlord'])
            ->post(route('finances.owners.statement.email', $owner->id), ['period' => '12'])
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    private function assertOwnerDeleted(PropertyOwner $owner): void
    {
        // Hard delete (no SoftDeletes); the nullOnDelete FK unassigns properties.
        $this->assertNull(PropertyOwner::find($owner->id));
    }
}
