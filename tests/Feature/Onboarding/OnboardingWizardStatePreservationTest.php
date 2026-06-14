<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression: every onboarding wizard advances steps via Inertia POST visits
 * (form.post / router.post), which default to preserveState:true. That keeps the
 * wizard component mounted across the success redirect, so useForm()'s
 * once-evaluated defaults stay frozen and the next step renders with the PREVIOUS
 * step's values instead of its own server props — showing stale/empty fields and
 * overwriting saved data on submit.
 *
 * The fix sets preserveState:'errors' on every navigation handler so a successful
 * save remounts and re-hydrates from the new step's props, while validation
 * errors still keep the user's input. This is a source contract because the
 * regression is purely client-side Inertia state the PHP layer cannot observe;
 * all four wizards (landlord/manager, caretaker, tenant, water-client) are
 * covered so the fix can't silently regress in one of them.
 */
class OnboardingWizardStatePreservationTest extends TestCase
{
    private function source(string $relPath): string
    {
        return (string) file_get_contents(resource_path($relPath));
    }

    /**
     * Slice from the handler marker through the end of its first Inertia post()
     * call (the closing `});`). Bounding to the call — rather than a fixed-size
     * window — keeps one handler's assertion from leaking into the next.
     */
    private function postCall(string $source, string $marker): string
    {
        $start = strpos($source, $marker);
        $this->assertNotFalse($start, "Expected to find {$marker} in the wizard source.");

        $end = strpos($source, '});', (int) $start);
        $this->assertNotFalse($end, "Expected a closing }); after {$marker}.");

        return substr($source, (int) $start, ((int) $end) - ((int) $start) + 3);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function wizardSubmitHandlers(): array
    {
        return [
            'landlord submit' => ['js/Pages/Onboarding/Index.vue', 'function submitStep()', 'onboarding.step.save'],
            'landlord skip' => ['js/Pages/Onboarding/Index.vue', 'function skipStep()', 'onboarding.step.skip'],
            'caretaker submit' => ['js/Pages/Onboarding/CaretakerSteps.vue', 'function submit()', 'onboarding.step.save'],
            'tenant submit' => ['js/Pages/Onboarding/TenantSteps.vue', 'function submit()', 'onboarding.step.save'],
            'water-client submit' => ['js/Pages/Onboarding/WaterClientSteps.vue', 'function submit()', 'onboarding.step.save'],
        ];
    }

    #[DataProvider('wizardSubmitHandlers')]
    public function test_wizard_handler_preserves_state_only_on_validation_errors(
        string $relPath,
        string $marker,
        string $route,
    ): void {
        $body = $this->postCall($this->source($relPath), $marker);

        $this->assertStringContainsString($route, $body, "{$relPath} {$marker} must POST to {$route}.");
        $this->assertStringContainsString(
            "preserveState: 'errors'",
            $body,
            "{$relPath} {$marker} must set preserveState: 'errors' so a successful save re-hydrates from server props.",
        );
    }
}
