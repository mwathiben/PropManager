<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Invitation;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\Onboarding\InvitationFunnelService;
use App\Services\Onboarding\OnboardingFunnelService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-77 FUNNEL + INVITE-FUNNEL: onboarding step funnel + invitation funnel +
 * the super-admin ops dashboard + the rollup command.
 */
class Phase77FunnelTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(string $role, int $currentStep, bool $completed = false): void
    {
        $user = User::factory()->create(['role' => $role]);
        OnboardingSession::create([
            'user_id' => $user->id,
            'role' => $role,
            'current_step' => $currentStep,
            'step_history' => [],
            'started_at' => now(),
            'last_touched_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    public function test_step_funnel_reached_counts_and_completion_rate(): void
    {
        $this->seedSession('caretaker', 2);
        $this->seedSession('caretaker', 4);
        $this->seedSession('caretaker', 5, completed: true);

        $funnel = app(OnboardingFunnelService::class)->forRole('caretaker');

        $this->assertSame(3, $funnel['total']);
        $this->assertSame(3, $funnel['steps'][0]['reached']); // step 1: all
        $this->assertSame(1, $funnel['steps'][4]['reached']); // step 5: only completed
        $this->assertEqualsWithDelta(33.3, $funnel['completion_rate'], 0.1);
        $this->assertNotNull($funnel['drop_off_step']);
    }

    public function test_invite_funnel_counts_and_acceptance_rate(): void
    {
        Model::withoutEvents(function () {
            Invitation::factory()->create(['role' => 'caretaker', 'viewed_at' => now(), 'accepted_at' => now()]);
            Invitation::factory()->create(['role' => 'caretaker', 'viewed_at' => now(), 'accepted_at' => null]);
            $old = Invitation::factory()->create(['role' => 'caretaker', 'accepted_at' => null]);
            $old->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
        });

        $funnel = app(InvitationFunnelService::class)->platform();

        $this->assertSame(3, $funnel['sent']);
        $this->assertSame(2, $funnel['viewed']);
        $this->assertSame(1, $funnel['accepted']);
        $this->assertSame(1, $funnel['expired']);
        $this->assertSame(1, $funnel['pending']);
        $this->assertEqualsWithDelta(33.3, $funnel['acceptance_rate'], 0.1);
    }

    public function test_ops_dashboard_is_super_admin_gated(): void
    {
        $this->seedSession('caretaker', 2);

        $admin = Model::withoutEvents(fn () => User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]));
        $response = $this->actingAs($admin)->get(route('ops.onboarding.funnel'));
        $response->assertOk();
        $this->assertArrayHasKey('funnels', $response->viewData('page')['props']);
        $this->assertArrayHasKey('inviteFunnel', $response->viewData('page')['props']);

        $landlord = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]));
        $this->actingAs($landlord)->get(route('ops.onboarding.funnel'))->assertForbidden();
    }

    public function test_funnel_rollup_command_runs(): void
    {
        $this->seedSession('caretaker', 2);

        $this->artisan('onboarding:funnel-rollup')->assertSuccessful();
    }
}
