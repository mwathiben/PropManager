<?php

declare(strict_types=1);

namespace Tests\Feature\Owners;

use App\Models\OwnerPayout;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\OwnerLedgerService;
use App\Services\OwnerStatementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-103 OWNER-PAYOUTS: management fee on the owner statement + PM->owner payout records
 * + a derived owner balance (lifetime net − non-voided payouts), landlord-side and portal.
 */
class Phase103OwnerPayoutsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function ownerUser(User $landlord): array
    {
        $user = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now(),
        ]));
        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $user->id]);

        return [$user, $owner];
    }

    // --- MANAGEMENT FEE --------------------------------------------------

    public function test_management_fee_helper_computes_percentage_flat_and_none(): void
    {
        $pct = PropertyOwner::factory()->make(['management_fee_type' => 'percentage', 'management_fee_value' => 10]);
        $this->assertEqualsWithDelta(3000.0, $pct->managementFeeOn(30000), 0.01);

        $flat = PropertyOwner::factory()->make(['management_fee_type' => 'flat', 'management_fee_value' => 2500]);
        $this->assertEqualsWithDelta(2500.0, $flat->managementFeeOn(30000), 0.01);

        $none = PropertyOwner::factory()->make(['management_fee_type' => 'none', 'management_fee_value' => 99]);
        $this->assertEqualsWithDelta(0.0, $none->managementFeeOn(30000), 0.01);
    }

    public function test_forowner_net_deducts_a_percentage_fee(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 30000);
        \App\Models\Expense::create([
            'landlord_id' => $landlord->id, 'building_id' => $setup['building']->id,
            'description' => 'Repairs', 'amount' => 5000, 'expense_date' => now(),
        ]);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(['management_fee_type' => 'percentage', 'management_fee_value' => 10]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, Carbon::now()->subMonth(), Carbon::now()->addDay());

        $this->assertEqualsWithDelta(30000.0, $data['collected'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $data['total_expenses'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $data['management_fee'], 0.01); // 10% of 30000
        $this->assertEqualsWithDelta(22000.0, $data['net'], 0.01); // 30000 - 5000 - 3000
    }

    public function test_forproperty_net_is_unchanged_by_the_owner_fee(): void
    {
        // Phase-100 non-regression: the per-property report (landlord-facing) must NOT
        // deduct the owner's management fee — that's the owner-facing statement's concern.
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 30000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(['management_fee_type' => 'percentage', 'management_fee_value' => 25]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        $data = app(OwnerStatementService::class)->forProperty($landlord->id, $setup['property']->id, Carbon::now()->subMonth(), Carbon::now()->addDay());

        $this->assertArrayNotHasKey('management_fee', $data);
        $this->assertEqualsWithDelta(30000.0, $data['net'], 0.01); // no fee deducted
    }

    public function test_default_owner_has_no_fee(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 20000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create();
        $setup['property']->update(['property_owner_id' => $owner->id]);

        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, Carbon::now()->subMonth(), Carbon::now()->addDay());
        $this->assertEqualsWithDelta(0.0, $data['management_fee'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $data['net'], 0.01);
    }

    // --- RECORD / VOID ---------------------------------------------------

    public function test_landlord_records_a_payout(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 12000,
                'paid_on' => now()->format('Y-m-d'),
                'method' => 'bank_transfer',
                'reference' => 'TT-9',
            ])->assertRedirect();

        $this->assertDatabaseHas('owner_payouts', [
            'landlord_id' => $setup['landlord']->id,
            'property_owner_id' => $owner->id,
            'amount' => 12000,
            'method' => 'bank_transfer',
            'reference' => 'TT-9',
            'created_by' => $setup['landlord']->id,
        ]);
    }

    public function test_void_excludes_a_payout_from_the_balance(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        $payout = OwnerPayout::factory()->forOwner($owner)->create(['amount' => 8000]);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.void', ['owner' => $owner->id, 'payout' => $payout->id]))
            ->assertRedirect();

        $this->assertNotNull($payout->fresh()->voided_at);
    }

    public function test_voiding_an_already_voided_payout_is_a_noop(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        $payout = OwnerPayout::factory()->forOwner($owner)->voided()->create();
        $voidedAt = $payout->voided_at;

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.void', ['owner' => $owner->id, 'payout' => $payout->id]))
            ->assertRedirect();

        $this->assertEquals($voidedAt->timestamp, $payout->fresh()->voided_at->timestamp);
    }

    public function test_cannot_record_a_payout_for_a_foreign_owner(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        $foreign = PropertyOwner::factory()->forLandlord($other['landlord'])->create();

        $status = $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $foreign->id), [
                'amount' => 5000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'cash',
            ])->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertDatabaseMissing('owner_payouts', ['property_owner_id' => $foreign->id]);
    }

    public function test_tenant_cannot_record_a_payout(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $this->actingAs($tenant->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 5000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'cash',
            ])->assertForbidden();
    }

    // --- BALANCE (derived) ----------------------------------------------

    public function test_balance_is_lifetime_net_minus_nonvoided_payouts(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 40000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create();
        $setup['property']->update(['property_owner_id' => $owner->id]);

        OwnerPayout::factory()->forOwner($owner)->create(['amount' => 15000]);
        OwnerPayout::factory()->forOwner($owner)->voided()->create(['amount' => 9999]); // excluded

        $summary = app(OwnerLedgerService::class)->summary($landlord->id, $owner->id);

        $this->assertEqualsWithDelta(40000.0, $summary['lifetime_net'], 0.01);
        $this->assertEqualsWithDelta(15000.0, $summary['total_paid_out'], 0.01); // voided one excluded
        $this->assertEqualsWithDelta(25000.0, $summary['balance_due'], 0.01);
    }

    public function test_overpayment_yields_a_negative_balance(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 10000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create();
        $setup['property']->update(['property_owner_id' => $owner->id]);
        OwnerPayout::factory()->forOwner($owner)->create(['amount' => 13000]); // advance

        $summary = app(OwnerLedgerService::class)->summary($landlord->id, $owner->id);
        $this->assertEqualsWithDelta(-3000.0, $summary['balance_due'], 0.01);
    }

    // --- LANDLORD SHOW ---------------------------------------------------

    public function test_landlord_show_renders_owner_detail_with_balance(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create();
        OwnerPayout::factory()->forOwner($owner)->create(['amount' => 5000]);

        $props = $this->actingAs($setup['landlord']->fresh())
            ->get(route('finances.owners.show', $owner->id))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Owners/Show', $props['component']);
        $this->assertSame($owner->id, $props['props']['owner']['id']);
        $this->assertCount(1, $props['props']['payouts']);
        $this->assertArrayHasKey('balance_due', $props['props']['summary']);
    }

    public function test_landlord_cannot_view_a_foreign_owner_detail(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        $foreign = PropertyOwner::factory()->forLandlord($other['landlord'])->create();

        $status = $this->actingAs($setup['landlord']->fresh())
            ->get(route('finances.owners.show', $foreign->id))
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
    }

    // --- OWNER PORTAL (read-only, scoped) --------------------------------

    public function test_owner_sees_only_their_own_payouts_in_the_portal(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        [$userA, $ownerA] = $this->ownerUser($landlord);
        OwnerPayout::factory()->forOwner($ownerA)->create(['amount' => 7000, 'reference' => 'A-PAYOUT']);

        [, $ownerB] = $this->ownerUser($landlord);
        OwnerPayout::factory()->forOwner($ownerB)->create(['amount' => 9000, 'reference' => 'B-PAYOUT']);

        $props = $this->actingAs($userA->fresh())
            ->get(route('owner-portal.payouts'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Owner/Payouts', $props['component']);
        $refs = collect($props['props']['payouts'])->pluck('reference')->all();
        $this->assertContains('A-PAYOUT', $refs);
        $this->assertNotContains('B-PAYOUT', $refs); // sibling owner's payout is invisible
    }

    public function test_portal_payouts_excludes_voided(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        [$user, $owner] = $this->ownerUser($setup['landlord']);
        OwnerPayout::factory()->forOwner($owner)->create(['amount' => 5000, 'reference' => 'LIVE']);
        OwnerPayout::factory()->forOwner($owner)->voided()->create(['amount' => 1000, 'reference' => 'DEAD']);

        $props = $this->actingAs($user->fresh())
            ->get(route('owner-portal.payouts'))
            ->assertOk()
            ->viewData('page');

        $refs = collect($props['props']['payouts'])->pluck('reference')->all();
        $this->assertContains('LIVE', $refs);
        $this->assertNotContains('DEAD', $refs);
    }

    public function test_non_owner_cannot_access_portal_payouts(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $this->actingAs($setup['landlord']->fresh())->get(route('owner-portal.payouts'))->assertForbidden();
    }
}
