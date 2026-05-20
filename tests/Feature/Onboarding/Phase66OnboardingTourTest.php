<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingMilestone;
use App\Models\User;
use App\Models\UserTourState;
use App\Services\Onboarding\TourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-66 ONBOARDING-TOUR CI: tour-key resolution, milestone-gated
 * step filtering, terminal-null payloads, monotonic advance, and the
 * server-authoritative advance/complete/dismiss endpoints.
 */
class Phase66OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TourService
    {
        return app(TourService::class);
    }

    private function landlord(): User
    {
        return User::factory()->create(['role' => 'landlord']);
    }

    private function reach(User $landlord, string ...$milestones): void
    {
        // Idempotent: UserObserver::created() already records SIGNED_UP
        // for a landlord (it refreshes to the DB-default role), so a
        // plain create() of signed_up would hit the unique constraint.
        foreach ($milestones as $milestone) {
            OnboardingMilestone::firstOrCreate(
                ['landlord_id' => $landlord->id, 'milestone' => $milestone],
                ['reached_at' => now()],
            );
        }
    }

    public function test_tour_key_resolves_per_role(): void
    {
        $svc = $this->service();
        $this->assertSame('landlord-dashboard', $svc->tourKeyForRole('landlord'));
        $this->assertSame('caretaker-intro', $svc->tourKeyForRole('caretaker'));
        $this->assertSame('tenant-intro', $svc->tourKeyForRole('tenant'));
        $this->assertNull($svc->tourKeyForRole('super_admin'));
        $this->assertNull($svc->tourKeyForRole(null));
    }

    public function test_fresh_landlord_payload_has_all_steps(): void
    {
        $payload = $this->service()->payloadFor($this->landlord());

        $this->assertNotNull($payload);
        $this->assertSame('landlord-dashboard', $payload['tour_key']);
        $this->assertTrue($payload['active']);
        $this->assertSame(0, $payload['current_step']);
        $this->assertCount(6, $payload['steps']);
        $this->assertSame('welcome', $payload['steps'][0]['key']);
        $this->assertNotSame('', $payload['steps'][0]['title']);
    }

    public function test_milestone_gating_drops_reached_steps(): void
    {
        $landlord = $this->landlord();
        $this->reach($landlord, OnboardingMilestone::FIRST_PROPERTY, OnboardingMilestone::FIRST_TENANT);

        $steps = $this->service()->stepsFor($landlord, 'landlord-dashboard');
        $keys = array_column($steps, 'key');

        $this->assertNotContains('add_building', $keys);   // gated by FIRST_PROPERTY
        $this->assertNotContains('invite_tenant', $keys);  // gated by FIRST_TENANT
        $this->assertSame(['welcome', 'add_unit', 'create_invoice', 'record_payment'], $keys);
    }

    public function test_ungated_welcome_step_survives_all_milestones(): void
    {
        $landlord = $this->landlord();
        $this->reach($landlord, ...OnboardingMilestone::FUNNEL);

        $payload = $this->service()->payloadFor($landlord);

        $this->assertNotNull($payload);
        $this->assertSame(['welcome'], array_column($payload['steps'], 'key'));
    }

    public function test_payload_is_null_once_dismissed(): void
    {
        $landlord = $this->landlord();
        $this->service()->dismiss($landlord, 'landlord-dashboard');

        $this->assertNull($this->service()->payloadFor($landlord));
    }

    public function test_payload_is_null_once_completed(): void
    {
        $landlord = $this->landlord();
        $this->service()->complete($landlord, 'landlord-dashboard');

        $this->assertNull($this->service()->payloadFor($landlord));
    }

    public function test_advance_cursor_is_monotonic(): void
    {
        $landlord = $this->landlord();

        $this->service()->advance($landlord, 'landlord-dashboard', 3);
        $this->assertSame(3, UserTourState::first()->current_step);

        // A replayed earlier step cannot rewind the cursor.
        $this->service()->advance($landlord, 'landlord-dashboard', 1);
        $this->assertSame(3, UserTourState::first()->current_step);
    }

    public function test_advance_is_rejected_after_completion(): void
    {
        $landlord = $this->landlord();
        $this->service()->complete($landlord, 'landlord-dashboard');

        $this->service()->advance($landlord, 'landlord-dashboard', 5);

        $state = UserTourState::first();
        $this->assertSame(UserTourState::STATUS_COMPLETED, $state->status);
        $this->assertSame(0, $state->current_step);
    }

    public function test_caretaker_and_tenant_have_three_step_tours(): void
    {
        $caretaker = User::factory()->create(['role' => 'caretaker']);
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->assertCount(3, $this->service()->payloadFor($caretaker)['steps']);
        $this->assertCount(3, $this->service()->payloadFor($tenant)['steps']);
    }

    public function test_super_admin_has_no_tour(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->assertNull($this->service()->payloadFor($admin));
    }

    public function test_advance_endpoint_persists_cursor(): void
    {
        $landlord = $this->landlord();

        $this->actingAs($landlord)
            ->post(route('onboarding-tour.advance'), ['step' => 2])
            ->assertRedirect();

        $state = UserTourState::where('user_id', $landlord->id)->first();
        $this->assertSame(2, $state->current_step);
        $this->assertSame(UserTourState::STATUS_ACTIVE, $state->status);
        $this->assertSame('landlord-dashboard', $state->tour_key);
    }

    public function test_complete_and_dismiss_endpoints_are_terminal(): void
    {
        // Create both users BEFORE any actingAs: minting a user while
        // authenticated as a landlord would let TenantScope's creating
        // hook rewrite the auto-recorded milestone's landlord_id.
        $landlord = $this->landlord();
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($landlord)->post(route('onboarding-tour.complete'))->assertRedirect();
        $this->assertSame(
            UserTourState::STATUS_COMPLETED,
            UserTourState::where('user_id', $landlord->id)->first()->status,
        );
        $this->assertNull($this->service()->payloadFor($landlord->fresh()));

        $this->actingAs($tenant)->post(route('onboarding-tour.dismiss'))->assertRedirect();
        $this->assertSame(
            UserTourState::STATUS_DISMISSED,
            UserTourState::where('user_id', $tenant->id)->first()->status,
        );
    }

    public function test_advance_validates_step(): void
    {
        $landlord = $this->landlord();

        $this->actingAs($landlord)->postJson(route('onboarding-tour.advance'), [])->assertStatus(422);
        $this->actingAs($landlord)->postJson(route('onboarding-tour.advance'), ['step' => 999])->assertStatus(422);
        $this->assertSame(0, UserTourState::count());
    }

    public function test_tour_endpoints_require_authentication(): void
    {
        $this->post(route('onboarding-tour.advance'), ['step' => 1])->assertRedirect();
        $this->post(route('onboarding-tour.complete'))->assertRedirect();
        $this->assertSame(0, UserTourState::count());
    }
}
