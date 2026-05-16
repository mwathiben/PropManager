<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Mail\ActivationNudgeMailable;
use App\Mail\DunningReminderMailable;
use App\Mail\TrialEndingMailable;
use App\Mail\WinbackMailable;
use App\Models\OnboardingProgress;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase34LifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_ending_reminder_targets_minus_3_day_window(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->trialing()->forUser($landlord)->forPlan($plan)->create([
            'trial_ends_at' => now()->addDays(3)->setTime(12, 0),
        ]);
        // Decoy: trial ending in 5 days — should NOT receive.
        Subscription::factory()->trialing()->forPlan($plan)->create([
            'trial_ends_at' => now()->addDays(5),
        ]);

        \Artisan::call('subscriptions:trial-ending-reminder');

        Mail::assertQueued(TrialEndingMailable::class, 1);
    }

    public function test_trial_ending_reminder_is_idempotent_for_same_day(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->trialing()->forUser($landlord)->forPlan($plan)->create([
            'trial_ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        \Artisan::call('subscriptions:trial-ending-reminder');
        \Artisan::call('subscriptions:trial-ending-reminder');

        Mail::assertQueued(TrialEndingMailable::class, 1);
    }

    public function test_dunning_email_targets_day_1_past_due(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->pastDue()->forUser($landlord)->forPlan($plan)->create();
        // Manually backdate updated_at so day-since calculation hits 1.
        $sub->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        \Artisan::call('subscriptions:dunning-emails');

        Mail::assertQueued(DunningReminderMailable::class, 1);
    }

    public function test_dunning_auto_cancels_at_day_14(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->pastDue()->forUser($landlord)->forPlan($plan)->create();
        $sub->forceFill(['updated_at' => now()->subDays(14)])->saveQuietly();

        \Artisan::call('subscriptions:dunning-emails');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status->value);
        $this->assertSame('technical_issues', $sub->cancel_reason);
    }

    public function test_winback_targets_day_7_post_cancel(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->forUser($landlord)->forPlan($plan)->create([
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(7)->setTime(12, 0),
        ]);
        // Decoy: cancelled 5 days ago — should NOT receive.
        Subscription::factory()->forPlan($plan)->create([
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(5),
        ]);

        \Artisan::call('subscriptions:churn-winback');

        Mail::assertQueued(WinbackMailable::class, 1);
    }

    public function test_activation_nudge_targets_stalled_progress(): void
    {
        Mail::fake();
        $landlord = User::factory()->create(['role' => 'landlord']);
        OnboardingProgress::create([
            'user_id' => $landlord->id,
            'current_step' => 2,
            'completed_steps' => [1],
            'last_touched_at' => now()->subDays(4),
        ]);
        // Decoy: fresh progress, should NOT receive.
        $fresh = User::factory()->create(['role' => 'landlord']);
        OnboardingProgress::create([
            'user_id' => $fresh->id,
            'current_step' => 2,
            'completed_steps' => [1],
            'last_touched_at' => now()->subHours(2),
        ]);

        \Artisan::call('landlords:activation-nudge');

        Mail::assertQueued(ActivationNudgeMailable::class, 1);
    }

    public function test_activation_nudge_skips_completed_progress(): void
    {
        Mail::fake();
        $landlord = User::factory()->create(['role' => 'landlord']);
        OnboardingProgress::create([
            'user_id' => $landlord->id,
            'current_step' => 6,
            'completed_steps' => [1, 2, 3, 4, 5],
            'last_touched_at' => now()->subDays(10),
            'completed_at' => now()->subDays(8),
        ]);

        \Artisan::call('landlords:activation-nudge');

        Mail::assertNothingQueued();
    }
}
