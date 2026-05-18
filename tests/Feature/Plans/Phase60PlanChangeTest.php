<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Enums\SubscriptionStatus;
use App\Events\PlanChanged;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanChange;
use App\Models\User;
use App\Services\Subscriptions\PlanChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-60 PLAN-CHANGE-1/2/3: self-serve upgrade/downgrade flow.
 * Writes an audit row regardless of Stripe outcome so support can
 * trace user intent vs Stripe state.
 */
class Phase60PlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_swaps_plan_and_writes_audit_row(): void
    {
        Event::fake();
        $user = User::factory()->create(['role' => 'landlord']);
        $oldPlan = SubscriptionPlan::factory()->create(['slug' => 'starter']);
        $newPlan = SubscriptionPlan::factory()->create(['slug' => 'pro']);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $oldPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $audit = app(PlanChangeService::class)->changePlan($sub, $newPlan, $user);

        $this->assertInstanceOf(SubscriptionPlanChange::class, $audit);
        $this->assertSame($oldPlan->id, $audit->from_plan_id);
        $this->assertSame($newPlan->id, $audit->to_plan_id);
        $this->assertSame($user->id, $audit->initiated_by);
        $this->assertTrue($audit->stripe_succeeded);

        $sub->refresh();
        $this->assertSame($newPlan->id, $sub->plan_id);

        Event::assertDispatched(PlanChanged::class);
    }

    public function test_service_writes_audit_even_when_no_stripe_binding(): void
    {
        // No stripe_subscription_code → service short-circuits Stripe
        // call but still writes audit + flips plan.
        $user = User::factory()->create(['role' => 'landlord']);
        $oldPlan = SubscriptionPlan::factory()->create();
        $newPlan = SubscriptionPlan::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $oldPlan->id,
            'status' => SubscriptionStatus::Active,
            'stripe_subscription_code' => null,
        ]);

        $audit = app(PlanChangeService::class)->changePlan($sub, $newPlan, $user);

        $this->assertTrue($audit->stripe_succeeded);
        $this->assertSame($newPlan->id, $sub->fresh()->plan_id);
    }

    public function test_service_does_not_flip_plan_when_stripe_target_missing_plan_code(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $oldPlan = SubscriptionPlan::factory()->create();
        $newPlan = SubscriptionPlan::factory()->create(['stripe_plan_code' => null]);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $oldPlan->id,
            'status' => SubscriptionStatus::Active,
            'stripe_subscription_code' => 'sub_test',
        ]);

        $audit = app(PlanChangeService::class)->changePlan($sub, $newPlan, $user);

        $this->assertFalse($audit->stripe_succeeded);
        $this->assertSame($oldPlan->id, $sub->fresh()->plan_id);
        $this->assertNotNull($audit->error_message);
    }

    public function test_change_route_rejects_no_active_subscription(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->actingAs($user)
            ->from('/subscription/plans')
            ->post('/subscription/change', ['new_plan_id' => $plan->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_change_route_rejects_same_plan(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->from('/subscription/plans')
            ->post('/subscription/change', ['new_plan_id' => $plan->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_change_route_swaps_plan_and_redirects(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $oldPlan = SubscriptionPlan::factory()->create();
        $newPlan = SubscriptionPlan::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $oldPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->post('/subscription/change', ['new_plan_id' => $newPlan->id]);

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success');

        $this->assertSame($newPlan->id, $user->fresh()->subscription->plan_id);
        $this->assertDatabaseHas('subscription_plan_changes', [
            'from_plan_id' => $oldPlan->id,
            'to_plan_id' => $newPlan->id,
            'initiated_by' => $user->id,
        ]);
    }
}
