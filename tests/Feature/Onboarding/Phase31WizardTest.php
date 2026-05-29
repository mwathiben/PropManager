<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase31WizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_step_records_skipped_distinct_from_completed(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->start();
        $progress->current_step = 6;
        $progress->save();

        $this->assertTrue($progress->skipStep(6));
        $progress->refresh();

        $this->assertContains(6, $progress->skipped_steps ?? []);
        $this->assertContains(6, $progress->completed_steps ?? []);
        $this->assertSame(7, $progress->current_step);
        $this->assertNotNull($progress->last_touched_at);
    }

    public function test_skip_non_optional_step_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->start();

        $this->assertFalse($progress->skipStep(3));
        $progress->refresh();

        $this->assertNotContains(3, $progress->skipped_steps ?? []);
    }

    public function test_complete_step_bumps_last_touched_at(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->start();
        $progress->last_touched_at = null;
        $progress->save();

        $progress->completeStep(2);
        $progress->refresh();

        $this->assertNotNull($progress->last_touched_at);
    }

    public function test_status_endpoint_returns_null_for_completed_user(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->markComplete();

        $this->actingAs($user)
            ->getJson('/api/onboarding/status')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_status_endpoint_returns_payload_for_in_progress_user(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->start();
        $progress->completeStep(1);
        $progress->refresh();

        $response = $this->actingAs($user)
            ->getJson('/api/onboarding/status')
            ->assertOk()
            ->json();

        $this->assertSame(2, $response['current_step']);
        $this->assertSame(8, $response['total_steps']);
        $this->assertGreaterThan(0, $response['completion_pct']);
        $this->assertStringContainsString('/onboarding/step/2', $response['resume_url']);
    }

    public function test_status_endpoint_requires_auth(): void
    {
        $this->getJson('/api/onboarding/status')->assertStatus(401);
    }

    public function test_wizard_audit_buckets_stalled_users(): void
    {
        $u1 = User::factory()->create(['role' => 'landlord']);
        $p1 = $u1->getOrCreateOnboardingProgress();
        $p1->start();
        $p1->last_touched_at = now()->subDays(2);
        $p1->save();

        $u2 = User::factory()->create(['role' => 'landlord']);
        $p2 = $u2->getOrCreateOnboardingProgress();
        $p2->start();
        $p2->last_touched_at = now()->subDays(40);
        $p2->save();

        $this->artisan('onboarding-wizard:audit')
            ->expectsOutputToContain('bucket=1-3')
            ->expectsOutputToContain('bucket=30+ ')
            ->assertSuccessful();
    }
}
