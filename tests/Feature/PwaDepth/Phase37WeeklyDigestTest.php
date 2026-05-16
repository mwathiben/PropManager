<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use App\Mail\WeeklyInsightDigestMailable;
use App\Models\NotificationPreference;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-37 PWA-DIGEST-1/2/3: insight:weekly-digest cron contract,
 * LifecycleOptInChecker integration, ISO-week idempotency, opt-in
 * default consent, dry-run path.
 */
class Phase37WeeklyDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_queues_for_active_paying_landlord_with_default_opt_in(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertQueued(
            WeeklyInsightDigestMailable::class,
            fn ($mail) => $mail->hasTo($landlord->email) && $mail->landlord->id === $landlord->id,
        );
    }

    public function test_digest_skipped_when_landlord_opted_out_of_lifecycle(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();
        NotificationPreference::query()->withoutGlobalScopes()->create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'email_enabled' => true,
            'lifecycle_enabled' => false,
        ]);

        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function test_digest_skipped_when_landlord_email_off(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();
        NotificationPreference::query()->withoutGlobalScopes()->create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'email_enabled' => false,
            'lifecycle_enabled' => true,
        ]);

        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function test_digest_skipped_for_cancelled_subscription(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->forUser($landlord)->forPlan($plan)->create([
            'cancelled_at' => now()->subDay(),
        ]);

        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function test_digest_idempotent_within_same_iso_week(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        $this->artisan('insight:weekly-digest')->assertExitCode(0);
        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertQueuedCount(1);
    }

    public function test_dry_run_does_not_queue_mail_but_still_consumes_cache_key(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        $this->artisan('insight:weekly-digest', ['--dry-run' => true])->assertExitCode(0);

        Mail::assertNothingQueued();
        $cacheKey = sprintf('insight:digest:%d:%s', $landlord->id, now()->format('o-W'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_digest_skips_non_landlord_role(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $tenant = User::factory()->create(['role' => 'tenant']);
        Subscription::factory()->active()->forUser($tenant)->forPlan($plan)->create();

        $this->artisan('insight:weekly-digest')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function test_mailable_subject_uses_pwa_digest_lang_key(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'name' => 'Acme Properties']);
        $summary = ['engagement_score' => 80, 'engagement_score_delta_7d' => 5];

        $mailable = new WeeklyInsightDigestMailable($landlord, $summary);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Acme Properties', $envelope->subject);
        $this->assertStringContainsString('PropManager insight', $envelope->subject);
    }
}
