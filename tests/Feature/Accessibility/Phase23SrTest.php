<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-SR-1: ARIA live announcer watchdog (WCAG 4.1.3
 * Status Messages). Pins that the announcer component carries both
 * politeness regions and that BOTH layouts mount it + wire Inertia
 * flash into it.
 */
class Phase23SrTest extends TestCase
{
    public function test_announcer_has_polite_and_assertive_regions(): void
    {
        $path = resource_path('js/Components/LiveAnnouncer.vue');
        $this->assertFileExists($path, 'A11Y-SR-1: LiveAnnouncer.vue must exist.');

        $component = file_get_contents($path);
        $this->assertStringContainsString(
            'aria-live="polite"',
            $component,
            'A11Y-SR-1: LiveAnnouncer must render an aria-live="polite" region.',
        );
        $this->assertStringContainsString(
            'aria-live="assertive"',
            $component,
            'A11Y-SR-1: LiveAnnouncer must render an aria-live="assertive" region.',
        );
        $this->assertStringContainsString(
            'role="alert"',
            $component,
            'A11Y-SR-1: the assertive region must carry role="alert".',
        );
        $this->assertStringContainsString(
            'sr-only',
            $component,
            'A11Y-SR-1: the announcer must be visually hidden (sr-only).',
        );
    }

    public function test_announcer_composable_exposes_announce(): void
    {
        $path = resource_path('js/composables/useAnnouncer.ts');
        $this->assertFileExists($path, 'A11Y-SR-1: useAnnouncer.ts must exist.');

        $composable = file_get_contents($path);
        $this->assertStringContainsString(
            'function announce(',
            $composable,
            'A11Y-SR-1: useAnnouncer must expose an announce() function.',
        );
        $this->assertStringContainsString(
            "politeness: Politeness = 'polite'",
            $composable,
            'A11Y-SR-1: announce() must accept a politeness argument defaulting to polite.',
        );
    }

    public function test_layouts_mount_live_announcer(): void
    {
        foreach (['AuthenticatedLayout', 'GuestLayout'] as $layout) {
            $contents = file_get_contents(resource_path("js/Layouts/{$layout}.vue"));

            $this->assertStringContainsString(
                'LiveAnnouncer',
                $contents,
                "A11Y-SR-1: {$layout} must import + mount LiveAnnouncer.",
            );
            $this->assertStringContainsString(
                'useAnnouncer',
                $contents,
                "A11Y-SR-1: {$layout} must use the announcer composable.",
            );
            $this->assertStringContainsString(
                'page.props.flash',
                $contents,
                "A11Y-SR-1: {$layout} must watch Inertia flash props and announce them.",
            );
        }
    }

    public function test_inertia_middleware_shares_flash(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString(
            "'flash'",
            $middleware,
            'A11Y-SR-1: HandleInertiaRequests must share a flash prop for the announcer.',
        );
        foreach (['success', 'error', 'message'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $middleware,
                "A11Y-SR-1: the shared flash prop must expose '{$key}'.",
            );
        }
    }
}
