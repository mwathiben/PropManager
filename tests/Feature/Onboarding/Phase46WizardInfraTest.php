<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingSession;
use App\Models\User;
use App\Onboarding\OnboardingFlow;
use App\Services\Onboarding\OnboardingSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-46 WIZARD-INFRA-1/2/3 watchdog suite.
 */
class Phase46WizardInfraTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_session_first_for_creates_a_row_if_none_active(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $session = OnboardingSession::firstFor($user);

        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('landlord', $session->role);
        $this->assertSame(1, $session->current_step);
        $this->assertNotNull($session->started_at);
        $this->assertTrue($session->isActive());
    }

    public function test_first_for_returns_existing_active_session(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $first = OnboardingSession::firstFor($user);
        $second = OnboardingSession::firstFor($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_first_for_creates_new_session_when_previous_is_abandoned(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $first = OnboardingSession::firstFor($user);
        $first->update(['abandoned_at' => now()]);

        $fresh = OnboardingSession::firstFor($user);

        $this->assertNotSame($first->id, $fresh->id, 'firstFor must mint a fresh session after abandonment');
        $this->assertTrue($fresh->isActive());
        $this->assertSame(1, $fresh->current_step);
        $this->assertSame(2, OnboardingSession::where('user_id', $user->id)->count(), 'historical row stays');
    }

    public function test_landlord_flow_has_8_steps(): void
    {
        $flow = OnboardingFlow::forRole('landlord');
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $flow->allSteps());
        $this->assertSame(1, $flow->firstStep());
        $this->assertSame(8, $flow->lastStep());
    }

    public function test_caretaker_flow_has_3_steps(): void
    {
        $flow = OnboardingFlow::forRole('caretaker');
        $this->assertSame([1, 2, 3], $flow->allSteps());
        $this->assertSame('Building assignment', $flow->stepLabel(2));
    }

    public function test_tenant_flow_has_3_steps(): void
    {
        $flow = OnboardingFlow::forRole('tenant');
        $this->assertSame([1, 2, 3], $flow->allSteps());
        $this->assertSame('KYC verification', $flow->stepLabel(2));
    }

    public function test_unsupported_role_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OnboardingFlow::forRole('super_admin');
    }

    public function test_next_step_returns_null_at_last_step(): void
    {
        $flow = OnboardingFlow::forRole('tenant');
        $this->assertNull($flow->nextStep(3));
    }

    public function test_previous_step_returns_null_at_first_step(): void
    {
        $flow = OnboardingFlow::forRole('landlord');
        $this->assertNull($flow->previousStep(1));
    }

    public function test_session_service_advance_runs_writer_in_transaction_and_appends_history(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);

        $advanced = app(OnboardingSessionService::class)->advance($session, 2, function ($s) {
            // No-op writer (canonical writes are out of scope for this test).
        });

        $this->assertSame(2, $advanced->current_step);
        $this->assertCount(1, $advanced->step_history);
        $this->assertSame('advance', $advanced->step_history[0]['action']);
    }

    public function test_session_service_advance_rolls_back_when_writer_throws(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);

        try {
            app(OnboardingSessionService::class)->advance($session, 2, function ($s) {
                throw new \RuntimeException('canonical write failed');
            });
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            // expected
        }

        $session->refresh();
        $this->assertSame(1, $session->current_step, 'failed writer must not advance the session');
    }

    public function test_session_service_back_appends_history_and_does_not_touch_canonical(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        app(OnboardingSessionService::class)->advance($session->fresh(), 2, fn ($s) => null);
        app(OnboardingSessionService::class)->advance($session->fresh(), 3, fn ($s) => null);

        $back = app(OnboardingSessionService::class)->back($session->fresh(), 2);

        $this->assertSame(2, $back->current_step);
        $this->assertCount(3, $back->step_history);
        $this->assertSame('back', $back->step_history[2]['action']);
    }

    public function test_session_service_advance_rejects_non_forward_target(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        app(OnboardingSessionService::class)->advance($session->fresh(), 3, fn ($s) => null);

        $this->expectException(\InvalidArgumentException::class);
        app(OnboardingSessionService::class)->advance($session->fresh(), 2, fn ($s) => null);
    }

    public function test_session_service_complete_sets_completed_at(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $session = OnboardingSession::firstFor($user);

        $completed = app(OnboardingSessionService::class)->complete($session);

        $this->assertNotNull($completed->completed_at);
        $this->assertFalse($completed->isActive());
        $this->assertSame(3, $completed->current_step);
    }
}
