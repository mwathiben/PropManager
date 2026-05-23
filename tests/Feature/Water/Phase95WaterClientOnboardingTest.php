<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Mail\WaterClientInvitation;
use App\Models\Invitation;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterConnection;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING: invite/provision + deep-link accept + the
 * water-client onboarding flow + a working (non-403) login landing.
 */
class Phase95WaterClientOnboardingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $setup['building']->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create(['landlord_id' => $this->landlord->id, 'water_billing_type' => 'consumption', 'water_unit_rate' => 150]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    private function connection(array $extra = []): WaterConnection
    {
        return WaterConnection::factory()->create(array_merge(['landlord_id' => $this->landlord->id], $extra));
    }

    private function waterClientUser(): User
    {
        return Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));
    }

    // --- INVITE ----------------------------------------------------------

    public function test_landlord_invites_a_water_client(): void
    {
        Mail::fake();
        $connection = $this->connection();

        $this->actingAs($this->landlord->fresh())
            ->post(route('water-client-invitations.store', $connection->id), ['email' => 'neighbour@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'neighbour@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'landlord_id' => $this->landlord->id,
        ]);
        Mail::assertQueued(WaterClientInvitation::class);
    }

    public function test_caretaker_cannot_invite(): void
    {
        $connection = $this->connection();
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $this->actingAs($caretaker->fresh())
            ->post(route('water-client-invitations.store', $connection->id), ['email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_cannot_invite_for_another_landlords_connection(): void
    {
        $otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
        $foreign = WaterConnection::factory()->create(['landlord_id' => $otherLandlord->id]);

        $response = $this->actingAs($this->landlord->fresh())
            ->post(route('water-client-invitations.store', $foreign->id), ['email' => 'x@example.com']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_duplicate_pending_invite_is_rejected(): void
    {
        Mail::fake();
        $connection = $this->connection();

        $this->actingAs($this->landlord->fresh())
            ->post(route('water-client-invitations.store', $connection->id), ['email' => 'dupe@example.com'])
            ->assertRedirect();

        $this->actingAs($this->landlord->fresh())
            ->post(route('water-client-invitations.store', $connection->id), ['email' => 'dupe@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Invitation::where('email', 'dupe@example.com')->count());
    }

    public function test_invite_email_is_normalized_to_lowercase(): void
    {
        Mail::fake();
        $connection = $this->connection();

        $this->actingAs($this->landlord->fresh())
            ->post(route('water-client-invitations.store', $connection->id), ['email' => 'Mixed@Example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', ['email' => 'mixed@example.com', 'role' => 'water_client']);
    }

    // --- SECURITY: water clients are landlord-provisioned, never self-registered ---

    public function test_water_client_invitation_cannot_self_register(): void
    {
        $connection = $this->connection();
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'neighbour@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'token' => Invitation::generateToken(),
        ]);

        // Password::defaults() includes ->uncompromised() (a real HIBP range call).
        // Fake an empty range response so a strong password clears validation and we
        // reach the role gate — deterministic, no network.
        \Illuminate\Support\Facades\Http::fake(['api.pwnedpasswords.com/*' => \Illuminate\Support\Facades\Http::response('', 200)]);

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

    public function test_register_page_redirects_water_client_token_to_deep_link(): void
    {
        $connection = $this->connection();
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'neighbour@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->get(route('register', ['invitation' => $invitation->token]))
            ->assertRedirect(route('water-invite.show', $invitation->token));
    }

    // --- ACCEPT ----------------------------------------------------------

    public function test_accept_creates_a_linked_water_client(): void
    {
        $connection = $this->connection();
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'neighbour@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->post(route('water-invite.accept', $invitation->token), [
            'name' => 'Neighbour Joe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('onboarding.step', ['step' => 1]));

        $user = User::where('email', 'neighbour@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('water_client', $user->role);
        $this->assertSame($this->landlord->id, (int) $user->landlord_id);
        $this->assertSame($user->id, (int) $connection->fresh()->user_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_accept_rejects_a_used_invitation(): void
    {
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'used@example.com',
            'role' => 'water_client',
            'token' => Invitation::generateToken(),
            'accepted_at' => now(),
        ]);

        $this->post(route('water-invite.accept', $invitation->token), [
            'name' => 'X',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'used@example.com']);
    }

    public function test_accept_refuses_when_connection_was_removed(): void
    {
        $connection = $this->connection();
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'orphan@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'token' => Invitation::generateToken(),
        ]);

        // Simulate the connection being removed after sending (FK nullOnDelete).
        $invitation->update(['water_connection_id' => null]);

        $this->post(route('water-invite.accept', $invitation->token), [
            'name' => 'Orphan',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'orphan@example.com']);
    }

    public function test_accept_does_not_overwrite_an_already_claimed_connection(): void
    {
        $existing = $this->waterClientUser();
        $connection = $this->connection(['user_id' => $existing->id]);
        $invitation = Invitation::create([
            'landlord_id' => $this->landlord->id,
            'email' => 'second@example.com',
            'role' => 'water_client',
            'water_connection_id' => $connection->id,
            'token' => Invitation::generateToken(),
        ]);

        $this->post(route('water-invite.accept', $invitation->token), [
            'name' => 'Second',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // The minted user is rolled back (link failed) and the original owner stands.
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
        $this->assertSame($existing->id, (int) $connection->fresh()->user_id);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    // --- LOGIN LANDING (no 403) ------------------------------------------

    public function test_water_client_lands_on_its_dashboard(): void
    {
        $waterClient = $this->waterClientUser();
        $connection = $this->connection(['user_id' => $waterClient->id, 'identifier' => 'LINE-WC-1']);

        $props = $this->actingAs($waterClient->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('WaterClient/Dashboard', $props['component']);
        $this->assertCount(1, $props['props']['connections']);
        $this->assertSame('LINE-WC-1', $props['props']['connections'][0]['identifier']);
    }

    // --- ONBOARDING FLOW -------------------------------------------------

    public function test_onboarding_profile_step_advances(): void
    {
        $waterClient = $this->waterClientUser();

        $this->actingAs($waterClient->fresh())
            ->post(route('onboarding.step.save', ['step' => 1]), ['name' => 'Renamed Client', 'mobile_number' => '0712345678'])
            ->assertRedirect(route('onboarding.step', ['step' => 2]));

        $this->assertSame('Renamed Client', $waterClient->fresh()->name);
    }

    public function test_onboarding_final_step_completes_to_dashboard(): void
    {
        $waterClient = $this->waterClientUser();

        // Step 3 is the final step (payment, acknowledgement-only) -> dashboard.
        $this->actingAs($waterClient->fresh())
            ->post(route('onboarding.step.save', ['step' => 3]), ['type' => ''])
            ->assertRedirect(route('dashboard'));
    }
}
