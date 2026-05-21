<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingResumeLink;
use App\Models\OnboardingSession;
use App\Onboarding\OnboardingFlow;
use App\Services\Onboarding\MirrorAuditService;
use App\Services\Onboarding\OnboardingResumeService;
use App\Services\Onboarding\OnboardingSessionService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-46 CI-1: consolidated onboarding-canonical surface watchdog.
 *
 * Per-finding tests cover individual surfaces; this is the integration
 * assertion that every Phase-46 invariant holds at once — same role
 * Phase24CiTest / Phase45TenantDepthSurfaceTest play.
 */
class Phase46OnboardingCanonicalSurfaceTest extends TestCase
{
    // -- CANONICAL-AUDIT -----------------------------------------------------

    public function test_mirror_registry_carries_profile_photo_path_entry(): void
    {
        $mirrors = collect(config('onboarding.mirrors'));

        $profilePhotoEntry = $mirrors->where('column', 'users.profile_photo_path')->first();
        $this->assertNotNull($profilePhotoEntry, 'CI-1: profile_photo_path must be registered.');
        $this->assertSame('landlord_profiles.profile_photo_path', $profilePhotoEntry['canonical']);
        $this->assertTrue($profilePhotoEntry['pinned']);
    }

    public function test_mirror_audit_service_exists_and_scans(): void
    {
        $results = app(MirrorAuditService::class)->scan();
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_audit_cron_registered(): void
    {
        $src = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString(
            "Schedule::command('onboarding:dedupe-audit')",
            $src,
            'CI-1: onboarding:dedupe-audit cron must be scheduled.',
        );
    }

    // -- CANONICAL-FIX -------------------------------------------------------

    public function test_kyc_completed_at_in_mirror_exempt_with_remove_at(): void
    {
        $exempt = collect(config('onboarding.mirror_exempt'))
            ->where('column', 'users.kyc_completed_at')
            ->first();
        $this->assertNotNull($exempt);
        $this->assertSame('2026-08-17', $exempt['remove_at']);
    }

    public function test_landlord_profile_carries_saved_listener(): void
    {
        $src = file_get_contents(app_path('Models/LandlordProfile.php'));
        $this->assertStringContainsString('static::saved', $src);
        $this->assertStringContainsString('profile_photo_path', $src);
    }

    public function test_user_model_exposes_canonical_accessors(): void
    {
        $src = file_get_contents(app_path('Models/User.php'));
        $this->assertStringContainsString('function kycVerifiedAt', $src);
        $this->assertStringContainsString('function canonicalNationalId', $src);
    }

    // -- WIZARD-INFRA --------------------------------------------------------

    public function test_onboarding_sessions_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('onboarding_sessions'));
        foreach (['user_id', 'role', 'current_step', 'step_history', 'started_at', 'last_touched_at', 'completed_at', 'abandoned_at', 'last_nudge_sent_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('onboarding_sessions', $col), "CI-1: onboarding_sessions.{$col} must exist.");
        }
    }

    public function test_onboarding_flow_dispatches_three_roles(): void
    {
        $this->assertCount(8, OnboardingFlow::forRole('landlord')->allSteps());
        // Phase-77 CARETAKER-FLOW-1: caretaker grew to 5 (welcome + orientation).
        $this->assertCount(5, OnboardingFlow::forRole('caretaker')->allSteps());
        $this->assertCount(3, OnboardingFlow::forRole('tenant')->allSteps());
    }

    public function test_onboarding_session_service_exists(): void
    {
        $this->assertTrue(class_exists(OnboardingSessionService::class));
        $service = new \ReflectionClass(OnboardingSessionService::class);
        foreach (['advance', 'back', 'complete', 'markAbandoned'] as $method) {
            $this->assertTrue($service->hasMethod($method), "CI-1: OnboardingSessionService::{$method} must exist.");
        }
    }

    // -- ROLE-PATHS ----------------------------------------------------------

    public function test_registered_user_controller_validates_role_input(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/RegisteredUserController.php'));
        $this->assertStringContainsString("in:landlord,caretaker,tenant", $src);
        $this->assertStringNotContainsString("\$user->role = 'tenant';", $src, 'CI-1: must not hardcode role to tenant.');
    }

    public function test_onboarding_routes_carry_verified_middleware(): void
    {
        $src = file_get_contents(base_path('routes/web.php'));
        $this->assertMatchesRegularExpression(
            "/Route::prefix\\('onboarding'\\)->middleware\\('verified'\\)/",
            $src,
        );
    }

    public function test_invitations_table_has_role_column(): void
    {
        $this->assertTrue(Schema::hasColumn('invitations', 'role'));
    }

    // -- PROGRESS-RESUME -----------------------------------------------------

    public function test_onboarding_resume_links_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('onboarding_resume_links'));
        foreach (['onboarding_session_id', 'signature_hash', 'signed_until', 'generated_at', 'consumed_at', 'consumed_from_ip'] as $col) {
            $this->assertTrue(Schema::hasColumn('onboarding_resume_links', $col));
        }
    }

    public function test_resume_service_exists_with_expiry_constant(): void
    {
        $this->assertTrue(class_exists(OnboardingResumeService::class));
        $this->assertSame(7, OnboardingResumeService::EXPIRY_DAYS);
    }

    public function test_nudge_cron_registered(): void
    {
        $src = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString(
            "Schedule::command('onboarding:nudge-stalled')",
            $src,
        );
    }

    public function test_resume_route_registered_with_signed_middleware(): void
    {
        $src = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString(
            "Route::get('/onboarding/resume/{session}'",
            $src,
        );
        $this->assertStringContainsString("->middleware('signed')", $src);
    }

    // -- CI ------------------------------------------------------------------

    public function test_alert_thresholds_md_has_phase_46_rows(): void
    {
        $src = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('canonical_mirror_drift_count', $src);
        $this->assertStringContainsString('onboarding_session_abandoned_count', $src);
    }

    public function test_onboarding_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/onboarding.md'));
    }
}
