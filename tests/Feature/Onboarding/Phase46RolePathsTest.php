<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Invitation;
use App\Models\OnboardingSession;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-46 ROLE-PATHS-1/2/3 watchdog suite.
 */
class Phase46RolePathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_signup_succeeds_with_role_in_form(): void
    {
        $this->post(route('register'), [
            'name' => 'New Landlord',
            'email' => 'new-landlord@example.test',
            'password' => 'CorrectHorseBatteryStaple1!',
            'password_confirmation' => 'CorrectHorseBatteryStaple1!',
            'role' => 'landlord',
        ]);

        $user = User::where('email', 'new-landlord@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('landlord', $user->role);
    }

    public function test_signup_defaults_to_tenant_when_role_omitted(): void
    {
        $this->post(route('register'), [
            'name' => 'Default User',
            'email' => 'default@example.test',
            'password' => 'CorrectHorseBatteryStaple1!',
            'password_confirmation' => 'CorrectHorseBatteryStaple1!',
        ]);

        $user = User::where('email', 'default@example.test')->first();
        $this->assertSame('tenant', $user->role);
    }

    public function test_invalid_role_choice_is_rejected(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Bad Role',
            'email' => 'bad@example.test',
            'password' => 'CorrectHorseBatteryStaple1!',
            'password_confirmation' => 'CorrectHorseBatteryStaple1!',
            'role' => 'super_admin',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_signup_via_invitation_token_uses_invitation_role(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $invitation = Invitation::create([
            'landlord_id' => $landlord->id,
            'email' => 'invitee@example.test',
            'role' => 'caretaker',
            'token' => 'invitation-token-abc',
            'property_id' => $property->id,
        ]);

        $this->post(route('register'), [
            'name' => 'Invited Caretaker',
            'email' => 'invitee@example.test',
            'password' => 'CorrectHorseBatteryStaple1!',
            'password_confirmation' => 'CorrectHorseBatteryStaple1!',
            'role' => 'landlord', // form says landlord, invitation overrides
            'invitation_token' => 'invitation-token-abc',
        ]);

        $user = User::where('email', 'invitee@example.test')->first();
        $this->assertSame('caretaker', $user->role, 'invitation role beats form choice');

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at, 'invitation must be marked accepted on signup');
    }

    public function test_signup_mints_an_onboarding_session(): void
    {
        $this->post(route('register'), [
            'name' => 'Session User',
            'email' => 'sess@example.test',
            'password' => 'CorrectHorseBatteryStaple1!',
            'password_confirmation' => 'CorrectHorseBatteryStaple1!',
            'role' => 'landlord',
        ]);

        $user = User::where('email', 'sess@example.test')->first();
        $session = OnboardingSession::where('user_id', $user->id)->first();

        $this->assertNotNull($session);
        $this->assertSame('landlord', $session->role);
        $this->assertSame(1, $session->current_step);
    }

    public function test_onboarding_routes_carry_verified_middleware(): void
    {
        // Source-level check: the onboarding group middleware list
        // must include 'verified' (Phase-46 ROLE-PATHS-2). A runtime
        // assertion would couple to OnboardingController::index's own
        // redirect behaviour (which has its own routing logic).
        $src = file_get_contents(base_path('routes/web.php'));

        // The group line must include 'verified' middleware between
        // prefix() and name().
        $this->assertMatchesRegularExpression(
            "/Route::prefix\\('onboarding'\\)->middleware\\('verified'\\)/",
            $src,
            'ROLE-PATHS-2: onboarding route group must carry verified middleware.',
        );
    }
}
