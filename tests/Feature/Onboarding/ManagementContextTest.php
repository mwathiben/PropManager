<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Onboarding\OnboardingFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Phase-2a: onboarding captures the user's management context at step 1 and
 * provisions the right scope-owner role (landlord vs manager). A manager
 * onboards exactly like a landlord — same 8-step flow.
 */
class ManagementContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_flow_mirrors_the_landlord_step_sequence(): void
    {
        $flow = OnboardingFlow::forRole('manager');

        foreach (range(1, 8) as $step) {
            $this->assertTrue(
                $flow->isValidStep($step),
                "manager flow must accept step {$step} like a landlord.",
            );
        }

        $this->assertSame(
            OnboardingFlow::forRole('landlord')->allSteps(),
            $flow->allSteps(),
            'manager flow must have the same step sequence as landlord.',
        );
    }

    public function test_manage_for_owners_context_upgrades_landlord_to_manager(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.step.save', ['step' => 1]), [
                'management_context' => 'manage_for_owners',
            ])
            ->assertRedirect();

        $fresh = $landlord->fresh();
        $this->assertSame('manager', $fresh->role);
        $this->assertSame((int) $fresh->id, (int) $fresh->landlord_id);
    }

    public function test_self_manage_context_keeps_landlord_role(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.step.save', ['step' => 1]), [
                'management_context' => 'self_manage',
            ])
            ->assertRedirect();

        $this->assertSame('landlord', $landlord->fresh()->role);
    }

    public function test_management_context_is_required_at_step_one(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.step.save', ['step' => 1]), [])
            ->assertSessionHasErrors('management_context');

        $this->assertSame('landlord', $landlord->fresh()->role);
    }

    public function test_resubmitting_step_one_is_idempotent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.step.save', ['step' => 1]), [
                'management_context' => 'manage_for_owners',
            ])->assertRedirect();

        $manager = $landlord->fresh();
        $this->assertSame('manager', $manager->role);

        $this->actingAs($manager)
            ->post(route('onboarding.step.save', ['step' => 1]), [
                'management_context' => 'manage_for_owners',
            ])->assertRedirect();

        $again = $landlord->fresh();
        $this->assertSame('manager', $again->role);
        $this->assertSame((int) $again->id, (int) $again->landlord_id);
    }

    public function test_step_one_renders_the_onboarding_page_for_a_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->get(route('onboarding.step', ['step' => 1]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Onboarding/Index'));
    }
}
