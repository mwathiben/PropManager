<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Mail\TrialEndingMailable;
use App\Models\NotificationPreference;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Platform\LifecycleOptInChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase35NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_checker_defaults_to_true_when_no_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertTrue(app(LifecycleOptInChecker::class)->allows($landlord));
    }

    public function test_lifecycle_checker_respects_opted_out_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationPreference::create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'lifecycle_enabled' => false,
            'email_enabled' => true,
        ]);

        $this->assertFalse(app(LifecycleOptInChecker::class)->allows($landlord));
    }

    public function test_lifecycle_checker_respects_email_disabled(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationPreference::create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'lifecycle_enabled' => true,
            'email_enabled' => false,
        ]);

        $this->assertFalse(app(LifecycleOptInChecker::class)->allows($landlord));
    }

    public function test_trial_ending_cron_skips_opted_out_landlord(): void
    {
        Mail::fake();
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->trialing()->forUser($landlord)->forPlan($plan)->create([
            'trial_ends_at' => now()->addDay()->setTime(12, 0),
        ]);
        NotificationPreference::create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'lifecycle_enabled' => false,
            'email_enabled' => true,
        ]);

        \Artisan::call('subscriptions:trial-ending-reminder');

        Mail::assertNotQueued(TrialEndingMailable::class);
    }

    public function test_preferences_endpoint_returns_matrix(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('preferences', $response);
        $this->assertArrayHasKey('lifecycle_enabled', $response['preferences']);
        $this->assertArrayHasKey('transactional_locked', $response);
        $this->assertContains('invoice', $response['transactional_locked']);
    }

    public function test_preferences_endpoint_toggles_lifecycle(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson('/api/v1/notifications/preferences', [
                'type' => 'lifecycle',
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $pref = NotificationPreference::query()->withoutGlobalScopes()
            ->where('user_id', $landlord->id)->first();
        $this->assertFalse((bool) $pref->lifecycle_enabled);
    }

    public function test_preferences_endpoint_rejects_disabling_transactional(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson('/api/v1/notifications/preferences', [
                'type' => 'invoice',
                'enabled' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'transactional_locked');
    }

    public function test_preferences_endpoint_rejects_unknown_type(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson('/api/v1/notifications/preferences', [
                'type' => 'made_up_type',
                'enabled' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_type');
    }

    public function test_drift_audit_flags_landlord_with_no_channels(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationPreference::create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'invoice_enabled' => true,
            'receipt_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => false,
        ]);

        $exit = \Artisan::call('notifications:preference-drift-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('drifted=1', $output);
    }

    public function test_drift_audit_flags_landlord_with_disabled_invoice(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationPreference::create([
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
            'invoice_enabled' => false, // transactional disabled
            'receipt_enabled' => true,
            'email_enabled' => true,
        ]);

        \Artisan::call('notifications:preference-drift-audit');
        $output = \Artisan::output();
        $this->assertStringContainsString('drifted=1', $output);
    }
}
