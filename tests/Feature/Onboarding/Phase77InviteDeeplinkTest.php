<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-77 INVITE-DEEPLINK: caretaker invitation accept routes into onboarding;
 * the invitation view stamps viewed_at once.
 */
class Phase77InviteDeeplinkTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $overrides = []): Invitation
    {
        return Model::withoutEvents(fn () => Invitation::factory()->create(array_merge([
            'role' => 'caretaker',
            'accepted_at' => null,
            'viewed_at' => null,
        ], $overrides)));
    }

    public function test_viewing_a_pending_invitation_stamps_viewed_at_once(): void
    {
        $invitation = $this->invitation();

        $this->get(route('invitations.show', $invitation->token))->assertOk();

        $first = $invitation->fresh()->viewed_at;
        $this->assertNotNull($first);

        $this->get(route('invitations.show', $invitation->token))->assertOk();
        $this->assertEquals($first, $invitation->fresh()->viewed_at);
    }

    public function test_new_caretaker_accept_redirects_into_onboarding(): void
    {
        $invitation = $this->invitation(['email' => 'newcaretaker@example.com']);

        $this->post(route('invitations.accept', $invitation->token), [
            'name' => 'New Caretaker',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
            'mobile_number' => '0700000000',
        ])->assertRedirect(route('onboarding.step', ['step' => 1]));

        $this->assertDatabaseHas('users', [
            'email' => 'newcaretaker@example.com',
            'role' => 'caretaker',
        ]);
    }

    public function test_authenticated_accept_redirects_into_onboarding(): void
    {
        $existing = User::factory()->create(['role' => 'caretaker', 'landlord_id' => null]);
        $invitation = $this->invitation(['target_user_id' => $existing->id]);

        $this->actingAs($existing)
            ->post(route('invitations.accept-authenticated', $invitation->id))
            ->assertRedirect(route('onboarding.step', ['step' => 1]));
    }
}
