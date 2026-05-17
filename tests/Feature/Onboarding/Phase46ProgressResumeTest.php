<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingResumeLink;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\Onboarding\OnboardingResumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase-46 PROGRESS-RESUME-1/2/3 watchdog suite.
 */
class Phase46ProgressResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_signed_url_and_audit_row(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);

        $url = app(OnboardingResumeService::class)->generate($session);

        $this->assertStringContainsString('/onboarding/resume/', $url);
        $this->assertStringContainsString('signature=', $url);

        $link = OnboardingResumeLink::query()
            ->where('onboarding_session_id', $session->id)
            ->first();
        $this->assertNotNull($link);
        $this->assertTrue($link->signed_until->isFuture());
        $this->assertNull($link->consumed_at);
    }

    public function test_consume_marks_link_consumed_and_records_ip(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $url = app(OnboardingResumeService::class)->generate($session);
        $signature = $this->extractSignatureFromUrl($url);

        app(OnboardingResumeService::class)->consume($session, $signature, '127.0.0.1');

        $link = OnboardingResumeLink::where('onboarding_session_id', $session->id)->first();
        $this->assertNotNull($link->consumed_at);
        $this->assertSame('127.0.0.1', $link->consumed_from_ip);
    }

    public function test_consume_rejects_replay(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $url = app(OnboardingResumeService::class)->generate($session);
        $signature = $this->extractSignatureFromUrl($url);

        app(OnboardingResumeService::class)->consume($session, $signature);

        $this->expectException(ValidationException::class);
        app(OnboardingResumeService::class)->consume($session, $signature);
    }

    public function test_consume_rejects_unknown_signature(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);

        $this->expectException(ValidationException::class);
        app(OnboardingResumeService::class)->consume($session, 'bogus-signature');
    }

    public function test_consume_rejects_expired_link(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $url = app(OnboardingResumeService::class)->generate($session);
        $signature = $this->extractSignatureFromUrl($url);

        // Backdate the audit row.
        OnboardingResumeLink::where('onboarding_session_id', $session->id)
            ->update(['signed_until' => now()->subDay()]);

        $this->expectException(ValidationException::class);
        app(OnboardingResumeService::class)->consume($session, $signature);
    }

    public function test_nudge_cron_emails_3_day_stalled_sessions(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $session->update(['last_touched_at' => now()->subDays(5)]);

        $this->artisan('onboarding:nudge-stalled')->assertExitCode(0);

        $session->refresh();
        $this->assertNotNull($session->last_nudge_sent_at);
        $this->assertSame(1, OnboardingResumeLink::where('onboarding_session_id', $session->id)->count());
    }

    public function test_nudge_cron_skips_fresh_sessions(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        // last_touched_at is now() — fresh.

        $this->artisan('onboarding:nudge-stalled')->assertExitCode(0);

        $session->refresh();
        $this->assertNull($session->last_nudge_sent_at);
    }

    public function test_nudge_cron_rate_limits_within_24h(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $session->update([
            'last_touched_at' => now()->subDays(5),
            'last_nudge_sent_at' => now()->subHours(2),
        ]);

        $this->artisan('onboarding:nudge-stalled')->assertExitCode(0);

        $this->assertSame(0, OnboardingResumeLink::where('onboarding_session_id', $session->id)->count());
    }

    public function test_nudge_cron_seals_30_day_abandoned_sessions(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $session->update(['last_touched_at' => now()->subDays(35)]);

        $this->artisan('onboarding:nudge-stalled')->assertExitCode(0);

        $session->refresh();
        $this->assertNotNull($session->abandoned_at);
    }

    private function extractSignatureFromUrl(string $url): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $q);

        return (string) ($q['signature'] ?? '');
    }
}
