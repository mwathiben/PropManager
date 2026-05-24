<?php

declare(strict_types=1);

namespace Tests\Feature\Owners;

use App\Mail\OwnerInvitation as OwnerInvitationMail;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-102 OWNER-PORTAL: invite an owner contact to a login, deep-link accept, and a
 * role-gated portal (dashboard + statements) that is strictly scoped to the authed
 * owner's own properties. Invite-only — never self-registerable.
 */
class Phase102OwnerPortalTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function ownerContact(array $extra = []): PropertyOwner
    {
        return PropertyOwner::factory()->forLandlord($this->landlord)->create($extra);
    }

    /** An owner that already has a login, plus its PropertyOwner contact. */
    private function ownerWithLogin(?Property $property = null): array
    {
        $user = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'owner',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));
        $owner = $this->ownerContact(['user_id' => $user->id]);

        if ($property !== null) {
            $property->update(['property_owner_id' => $owner->id]);
        }

        return [$user, $owner];
    }

    // --- INVITE ----------------------------------------------------------

    public function test_landlord_invites_an_owner(): void
    {
        Mail::fake();
        $owner = $this->ownerContact(['email' => 'owner@example.com']);

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'owner@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'landlord_id' => $this->landlord->id,
        ]);
        Mail::assertQueued(OwnerInvitationMail::class);
    }

    public function test_cannot_invite_an_owner_without_an_email(): void
    {
        Mail::fake();
        $owner = $this->ownerContact(['email' => null]);

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertSessionHasErrors('email');

        Mail::assertNothingQueued();
    }

    public function test_cannot_invite_an_owner_that_already_has_a_login(): void
    {
        [, $owner] = $this->ownerWithLogin();

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertStatus(422);
    }

    public function test_cannot_invite_a_foreign_landlords_owner(): void
    {
        $other = $this->createLandlordWithFullSetup()['landlord'];
        $foreign = PropertyOwner::factory()->forLandlord($other)->create(['email' => 'foreign@example.com']);

        $status = $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $foreign->id))
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertDatabaseMissing('invitations', ['property_owner_id' => $foreign->id]);
    }

    public function test_duplicate_pending_invite_is_rejected(): void
    {
        Mail::fake();
        $owner = $this->ownerContact(['email' => 'dupe@example.com']);

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertRedirect();

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Invitation::where('property_owner_id', $owner->id)->count());
    }

    public function test_cannot_invite_when_email_already_belongs_to_a_user(): void
    {
        Model::withoutEvents(fn () => User::factory()->create(['email' => 'taken@example.com']));
        $owner = $this->ownerContact(['email' => 'taken@example.com']);

        $this->actingAs($this->landlord->fresh())
            ->post(route('finances.owners.invite', $owner->id))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('invitations', ['property_owner_id' => $owner->id]);
    }

    // --- SECURITY: owners are invite-only, never self-registered ----------

    public function test_owner_invitation_cannot_self_register(): void
    {
        $owner = $this->ownerContact(['email' => 'owner@example.com']);
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'owner@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
        ]);

        // Fake an empty HIBP range so a strong password clears uncompromised() and we
        // actually reach the role gate (deterministic, no network).
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        $this->post(route('register'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'invitation_token' => $invitation->token,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
        // The one-time token must NOT be burned by the rejected attempt.
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_register_page_redirects_owner_token_to_deep_link(): void
    {
        $owner = $this->ownerContact(['email' => 'owner@example.com']);
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'owner@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->get(route('register', ['invitation' => $invitation->token]))
            ->assertRedirect(route('owner-invite.show', $invitation->token));
    }

    // --- ACCEPT ----------------------------------------------------------

    public function test_accept_creates_a_linked_owner_login(): void
    {
        $owner = $this->ownerContact(['email' => 'owner@example.com']);
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'owner@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->post(route('owner-invite.accept', $invitation->token), [
            'name' => 'Acme Holdings',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'mobile_number' => '0712345678',
        ])->assertRedirect(route('owner-portal.dashboard'));

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('owner', $user->role);
        $this->assertSame($this->landlord->id, (int) $user->landlord_id);
        $this->assertSame($user->id, (int) $owner->fresh()->user_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_accept_rejects_a_used_invitation(): void
    {
        $owner = $this->ownerContact(['email' => 'used@example.com']);
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'used@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
            'accepted_at' => now(),
        ]);

        $this->post(route('owner-invite.accept', $invitation->token), [
            'name' => 'X',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'used@example.com']);
        $this->assertNull($owner->fresh()->user_id);
    }

    public function test_accept_refuses_when_owner_contact_was_removed(): void
    {
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'orphan@example.com',
            'role' => 'owner',
            'property_owner_id' => null, // contact deleted after sending (nullOnDelete)
            'token' => Invitation::generateToken(),
        ]);

        $this->post(route('owner-invite.accept', $invitation->token), [
            'name' => 'Orphan',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'orphan@example.com']);
    }

    public function test_accept_does_not_overwrite_an_already_claimed_owner(): void
    {
        [$existing, $owner] = $this->ownerWithLogin();
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'second@example.com',
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->post(route('owner-invite.accept', $invitation->token), [
            'name' => 'Second',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // The minted user is rolled back (link failed) and the original owner stands.
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
        $this->assertSame($existing->id, (int) $owner->fresh()->user_id);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    // --- LOGIN LANDING ---------------------------------------------------

    public function test_owner_dashboard_redirects_owner_to_portal(): void
    {
        [$user] = $this->ownerWithLogin();

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('owner-portal.dashboard'));
    }

    // --- PORTAL: SCOPING ISOLATION (the priority) ------------------------

    public function test_owner_dashboard_shows_only_their_own_properties(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        // Two owners under the SAME landlord, each with their own property.
        $userA = Model::withoutEvents(fn () => User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]));
        $ownerA = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $userA->id]);
        $setup['property']->update(['property_owner_id' => $ownerA->id]);

        $userB = Model::withoutEvents(fn () => User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]));
        $ownerB = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $userB->id]);
        $propB = Property::factory()->create(['landlord_id' => $landlord->id, 'name' => 'Owner-B Tower', 'property_owner_id' => $ownerB->id]);

        $props = $this->actingAs($userA->fresh())
            ->get(route('owner-portal.dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Owner/Dashboard', $props['component']);
        $names = collect($props['props']['properties'])->pluck('name')->all();
        $this->assertContains($setup['property']->name, $names);
        $this->assertNotContains('Owner-B Tower', $names); // cannot see the sibling owner's property
    }

    public function test_owner_statement_only_aggregates_their_own_properties(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());
        $this->createPaymentWithInvoice($lease, 30000);

        $user = Model::withoutEvents(fn () => User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]));
        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $user->id]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        // A sibling owner with revenue the authed owner must NOT see.
        $userB = Model::withoutEvents(fn () => User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]));
        $ownerB = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $userB->id]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'property_owner_id' => $ownerB->id]);

        $props = $this->actingAs($user->fresh())
            ->get(route('owner-portal.statements', ['period' => '12']))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Owner/Statements', $props['component']);
        $statement = $props['props']['statement'];
        $this->assertEqualsWithDelta(30000.0, $statement['collected'], 0.01);
        $this->assertCount(1, $statement['properties']); // only the authed owner's property
    }

    public function test_unknown_period_falls_back_to_twelve_months_not_this_month(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        // Revenue dated ~6 months ago: included by a 12-month window, excluded by a
        // "this month so far" window. A crafted ?period=custom (no dates) must NOT
        // silently collapse to this month and drop it.
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 22000);
        $payment->forceFill(['payment_date' => now()->subMonths(6)])->save();

        $user = Model::withoutEvents(fn () => User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]));
        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(['user_id' => $user->id]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        $statement = $this->actingAs($user->fresh())
            ->get(route('owner-portal.statements', ['period' => 'custom']))
            ->assertOk()
            ->viewData('page')['props']['statement'];

        $this->assertEqualsWithDelta(22000.0, $statement['collected'], 0.01);
    }

    public function test_owner_statement_download_returns_pdf(): void
    {
        [$user, $owner] = $this->ownerWithLogin($this->createLandlordWithFullSetup()['property']);

        $pdf = $this->actingAs($user->fresh())
            ->get(route('owner-portal.statements.download', ['period' => '12']));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    // --- PORTAL: ROLE GATING ---------------------------------------------

    public function test_non_owner_roles_cannot_access_the_owner_portal(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $this->actingAs($setup['landlord']->fresh())->get(route('owner-portal.dashboard'))->assertForbidden();
        $this->actingAs($tenant->fresh())->get(route('owner-portal.dashboard'))->assertForbidden();
        $this->actingAs($setup['landlord']->fresh())->get(route('owner-portal.statements'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_the_portal(): void
    {
        $this->get(route('owner-portal.dashboard'))->assertRedirect(route('login'));
    }
}
