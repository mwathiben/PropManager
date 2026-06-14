<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

/**
 * Regression: the onboarding wizard advances steps via Inertia POST visits
 * (form.post / router.post), which default to preserveState:true. That keeps
 * the single Onboarding/Index component mounted across the success redirect, so
 * useForm()'s once-evaluated defaults stay frozen and the next step renders with
 * the PREVIOUS step's values instead of its own server props — showing stale or
 * empty fields to returning landlords and overwriting saved data on submit.
 *
 * The fix sets preserveState:'errors' on both navigation calls so a successful
 * save remounts and re-hydrates from the new step's props, while validation
 * errors still keep the user's input. This is a source contract because the
 * regression is purely client-side Inertia state the PHP layer cannot observe.
 */
class OnboardingWizardStatePreservationTest extends TestCase
{
    private function wizardSource(): string
    {
        return (string) file_get_contents(
            resource_path('js/Pages/Onboarding/Index.vue')
        );
    }

    private function handlerBody(string $source, string $marker): string
    {
        $start = strpos($source, $marker);
        $this->assertNotFalse($start, "Expected to find {$marker} in Onboarding/Index.vue.");

        return substr($source, (int) $start, 400);
    }

    public function test_submit_step_preserves_state_only_on_validation_errors(): void
    {
        $submit = $this->handlerBody($this->wizardSource(), 'function submitStep()');

        $this->assertStringContainsString('onboarding.step.save', $submit);
        $this->assertStringContainsString("preserveState: 'errors'", $submit);
    }

    public function test_skip_step_preserves_state_only_on_validation_errors(): void
    {
        $skip = $this->handlerBody($this->wizardSource(), 'function skipStep()');

        $this->assertStringContainsString('onboarding.step.skip', $skip);
        $this->assertStringContainsString("preserveState: 'errors'", $skip);
    }
}
