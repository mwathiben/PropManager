<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Onboarding\OnboardingFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-77 ONBOARDING-DEEP-2 surface watchdog — cross-category presence map a
 * refactor cannot silently regress.
 */
class Phase77OnboardingDeep2SurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- CARETAKER-FLOW + CONTEXT ------------------------------------------

    public function test_caretaker_flow_is_five_steps_and_services_present(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], OnboardingFlow::forRole('caretaker')->allSteps());
        $this->assertTrue(class_exists(\App\Services\Caretaker\CaretakerBuildingSummaryService::class));
        $this->assertTrue(class_exists(\App\Services\Caretaker\CaretakerFirstTaskResolver::class));
    }

    public function test_caretaker_steps_vue_has_welcome_and_orientation_tokens(): void
    {
        $vue = (string) file_get_contents(base_path('resources/js/Pages/Onboarding/CaretakerSteps.vue'));
        $this->assertStringContainsString('caretaker-welcome', $vue);
        $this->assertStringContainsString('caretaker-orientation', $vue);

        foreach (['en', 'sw', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('onboarding.caretaker.title', __('onboarding.caretaker.title'));
            $this->assertNotSame('onboarding.caretaker.orientation_cta', __('onboarding.caretaker.orientation_cta'));
        }
    }

    // -- INVITE-DEEPLINK ---------------------------------------------------

    public function test_invitation_viewed_at_column_present(): void
    {
        $this->assertTrue(Schema::hasColumn('invitations', 'viewed_at'));
    }

    // -- FUNNEL + INVITE-FUNNEL --------------------------------------------

    public function test_funnel_services_route_and_cron_present(): void
    {
        $this->assertTrue(class_exists(\App\Services\Onboarding\OnboardingFunnelService::class));
        $this->assertTrue(class_exists(\App\Services\Onboarding\InvitationFunnelService::class));
        $this->assertTrue(Route::has('ops.onboarding.funnel'));
        $this->assertFileExists(base_path('resources/js/Pages/Ops/Onboarding/Funnel.vue'));

        $commands = collect(Schedule::events())->map(fn ($e) => (string) $e->command)->implode(' ');
        $this->assertStringContainsString('onboarding:funnel-rollup', $commands);
    }

    public function test_rollup_emits_gauges_and_alert_registered(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/OnboardingFunnelRollup.php'));
        $this->assertStringContainsString('onboarding_completion_rate', $source);
        $this->assertStringContainsString('invitation_acceptance_rate', $source);

        $alertKeys = collect(config('alerts.alerts'))->pluck('key');
        $this->assertTrue($alertKeys->contains('onboarding_completion_low'));
    }

    // -- CI ----------------------------------------------------------------

    public function test_runbook_mentions_phase_77(): void
    {
        $runbook = (string) file_get_contents(base_path('docs/runbooks/onboarding.md'));
        $this->assertStringContainsString('Phase 77', $runbook);
        $this->assertStringContainsString('ONBOARDING-DEEP-2', $runbook);
    }
}
