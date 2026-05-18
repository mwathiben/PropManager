<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Enums\SubscriptionStatus;
use App\Listeners\StartTrialOnLandlordRegistered;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\TrialStartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-60 TRIAL-DEPTH-1/2/3: trial start service + Registered
 * listener + auto-expire cron.
 */
class Phase60TrialDepthTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_trial_for_creates_14d_subscription_on_default_plan(): void
    {
        SubscriptionPlan::factory()->create(['slug' => 'starter']);
        $user = User::factory()->create(['role' => 'landlord']);

        $sub = app(TrialStartService::class)->startTrialFor($user);

        $this->assertNotNull($sub);
        $this->assertSame(SubscriptionStatus::Trialing, $sub->status);
        $this->assertTrue($sub->trial_ends_at->isFuture());
        $this->assertSame(14, (int) round(now()->diffInDays($sub->trial_ends_at)));
    }

    public function test_start_trial_for_returns_null_for_non_landlord(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->assertNull(app(TrialStartService::class)->startTrialFor($tenant));
    }

    public function test_start_trial_for_is_idempotent_when_subscription_exists(): void
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'starter']);
        $user = User::factory()->create(['role' => 'landlord']);
        $existing = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $sub = app(TrialStartService::class)->startTrialFor($user->fresh());

        $this->assertSame($existing->id, $sub->id);
        $this->assertSame(SubscriptionStatus::Active, $sub->status);
    }

    public function test_registered_listener_mints_trial_for_landlord(): void
    {
        SubscriptionPlan::factory()->create(['slug' => 'starter']);
        $user = User::factory()->create(['role' => 'landlord']);

        app(StartTrialOnLandlordRegistered::class)->handle(new Registered($user));

        $this->assertNotNull($user->fresh()->subscription);
        $this->assertSame(SubscriptionStatus::Trialing, $user->fresh()->subscription->status);
    }

    public function test_auto_expire_command_transitions_stale_trials(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('trial:auto-expire')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $sub->status);
        $this->assertSame('trial_expired', $sub->cancel_reason);
    }

    public function test_auto_expire_command_skips_in_window_trials(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->artisan('trial:auto-expire')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Trialing, $sub->status);
    }

    public function test_auto_expire_scheduled_at_0930(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'trial:auto-expire'));

        $this->assertNotNull($entry);
        $this->assertSame('30 9 * * *', $entry->expression);
    }
}
