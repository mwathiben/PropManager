<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Mail\OnboardingResumeMailable;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\Onboarding\CaretakerOnboardingService;
use App\Services\Onboarding\OnboardingStepProcessor;
use App\Services\Onboarding\TenantOnboardingService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-47 CI-1: consolidated wizard-migrate surface watchdog.
 *
 * Per-finding tests cover individual surfaces; this is the integration
 * assertion that every Phase-47 invariant holds at once — same role
 * Phase46OnboardingCanonicalSurfaceTest plays.
 */
class Phase47WizardMigrateSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- LANDLORD-MIGRATE / STEP-DATA-DEPRECATE ------------------------------

    public function test_onboarding_service_no_longer_writes_step_data(): void
    {
        $src = file_get_contents(base_path('app/Services/OnboardingService.php'));
        $this->assertStringNotContainsString(
            '$progress->saveStepData(',
            $src,
            'LANDLORD-MIGRATE: OnboardingService must not call saveStepData() anywhere.',
        );
    }

    public function test_onboarding_service_no_longer_reads_step_data(): void
    {
        $src = file_get_contents(base_path('app/Services/OnboardingService.php'));
        $this->assertStringNotContainsString(
            '$progress->getStepData(',
            $src,
            'STEP-DATA-DEPRECATE-2/3: OnboardingService must read canonical models instead of getStepData().',
        );
    }

    public function test_step_data_is_listed_in_mirror_exempt_with_remove_at(): void
    {
        $exempt = collect(config('onboarding.mirror_exempt'));
        $entry = $exempt->where('column', 'onboarding_progress.step_data')->first();

        $this->assertNotNull($entry, 'STEP-DATA-DEPRECATE-1: step_data must be listed in mirror_exempt.');
        $this->assertSame('2026-08-17', $entry['remove_at']);
    }

    public function test_onboarding_service_implements_step_processor(): void
    {
        $this->assertInstanceOf(OnboardingStepProcessor::class, app(OnboardingService::class));
    }

    // -- ROLE-DISPATCH -------------------------------------------------------

    public function test_controller_uses_onboarding_flow(): void
    {
        $src = file_get_contents(base_path('app/Http/Controllers/OnboardingController.php'));
        $this->assertStringContainsString(
            'OnboardingFlow::forRole',
            $src,
            'ROLE-DISPATCH-1: OnboardingController must validate step bounds against OnboardingFlow.',
        );
    }

    public function test_controller_uses_session_service_advance(): void
    {
        $src = file_get_contents(base_path('app/Http/Controllers/OnboardingController.php'));
        $this->assertStringContainsString(
            '$this->sessionService->advance',
            $src,
            'LANDLORD-MIGRATE-1: OnboardingController::saveStep must route through OnboardingSessionService::advance.',
        );
    }

    public function test_tenant_step_processor_exists_and_handles_three_steps(): void
    {
        $service = app(TenantOnboardingService::class);
        $this->assertInstanceOf(OnboardingStepProcessor::class, $service);

        $tenant = User::factory()->create(['role' => 'tenant']);
        $progress = $tenant->getOrCreateOnboardingProgress();

        $this->assertTrue($service->processStep(1, ['name' => 'Jane Mwangi'], $tenant, $progress));
        $this->assertSame('Jane Mwangi', $tenant->fresh()->name);

        $this->assertTrue($service->processStep(2, ['acknowledged' => true], $tenant, $progress));
        $this->assertTrue($service->processStep(3, ['acknowledged' => true], $tenant, $progress));
    }

    public function test_caretaker_step_processor_exists_and_handles_three_steps(): void
    {
        $service = app(CaretakerOnboardingService::class);
        $this->assertInstanceOf(OnboardingStepProcessor::class, $service);

        $caretaker = User::factory()->create(['role' => 'caretaker']);
        $progress = $caretaker->getOrCreateOnboardingProgress();

        $this->assertTrue($service->processStep(1, ['name' => 'Peter Kamau'], $caretaker, $progress));
        $this->assertSame('Peter Kamau', $caretaker->fresh()->name);

        $this->assertTrue($service->processStep(2, ['acknowledged' => true], $caretaker, $progress));
        $this->assertTrue($service->processStep(3, ['email_enabled' => true, 'sms_enabled' => false], $caretaker, $progress));
    }

    // -- MAIL-DISPATCH -------------------------------------------------------

    public function test_nudge_cron_dispatches_onboarding_resume_mailable(): void
    {
        $src = file_get_contents(base_path('app/Console/Commands/NudgeStalledOnboarding.php'));
        $this->assertStringContainsString(
            'OnboardingResumeMailable',
            $src,
            'MAIL-DISPATCH-2: NudgeStalledOnboarding must reference OnboardingResumeMailable.',
        );
        $this->assertStringContainsString(
            'Mail::to(',
            $src,
            'MAIL-DISPATCH-2: NudgeStalledOnboarding must dispatch via Mail::to(...)->queue(...).',
        );
    }

    public function test_onboarding_resume_mailable_subject_renders_from_lang(): void
    {
        $user = User::factory()->create(['role' => 'landlord', 'locale' => 'en']);
        $session = OnboardingSession::firstFor($user);

        $mailable = new OnboardingResumeMailable('https://example.test/resume/123', $session);
        $envelope = $mailable->envelope();

        $this->assertSame(__('onboarding.nudge.subject'), $envelope->subject);
    }

    public function test_nudge_cron_actually_queues_mail_for_stalled_session(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => 'landlord']);
        $session = OnboardingSession::firstFor($user);
        $session->update(['last_touched_at' => now()->subDays(5)]);

        $this->artisan('onboarding:nudge-stalled')->assertExitCode(0);

        Mail::assertQueued(OnboardingResumeMailable::class, 1);
    }

    // -- WIZARD-VUE-MINIMAL --------------------------------------------------

    public function test_index_vue_dispatches_to_tenant_and_caretaker_scaffolds(): void
    {
        $src = file_get_contents(base_path('resources/js/Pages/Onboarding/Index.vue'));
        $this->assertStringContainsString(
            'TenantSteps',
            $src,
            'WIZARD-VUE-1: Index.vue must import TenantSteps.',
        );
        $this->assertStringContainsString(
            'CaretakerSteps',
            $src,
            'WIZARD-VUE-1: Index.vue must import CaretakerSteps.',
        );
        $this->assertTrue(
            file_exists(base_path('resources/js/Pages/Onboarding/TenantSteps.vue')),
            'WIZARD-VUE-2: TenantSteps.vue scaffold must exist.',
        );
        $this->assertTrue(
            file_exists(base_path('resources/js/Pages/Onboarding/CaretakerSteps.vue')),
            'WIZARD-VUE-2: CaretakerSteps.vue scaffold must exist.',
        );
    }

    // -- LANG PARITY ---------------------------------------------------------

    public function test_onboarding_nudge_keys_in_en_sw_ar(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $bundle = require base_path("lang/{$locale}/onboarding.php");
            $this->assertArrayHasKey('nudge', $bundle, "Locale {$locale} must carry the nudge namespace.");
            foreach (['subject', 'heading', 'greeting', 'body', 'cta'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $bundle['nudge'],
                    "Locale {$locale} must carry onboarding.nudge.{$key}.",
                );
            }
        }
    }

    // -- RUNBOOK -------------------------------------------------------------

    public function test_runbook_has_phase_47_section(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/onboarding.md'));
        $this->assertStringContainsString(
            'Phase-47',
            $md,
            'CI-2: docs/runbooks/onboarding.md must carry a Phase-47 [WIZARD-MIGRATE] section.',
        );
    }
}
