<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-KBD-1: skip-link watchdog (WCAG 2.4.1 Bypass Blocks).
 *
 * Source-level assertions in the Phase-22 watchdog style — the
 * skip-link + the focusable <main> target are exactly the kind of
 * structural win that silently disappears in a layout refactor.
 */
class Phase23KbdTest extends TestCase
{
    private function authenticatedLayout(): string
    {
        $path = resource_path('js/Layouts/AuthenticatedLayout.vue');
        $this->assertFileExists($path, 'AuthenticatedLayout.vue must exist.');

        return file_get_contents($path);
    }

    public function test_authenticated_layout_has_skip_link(): void
    {
        $layout = $this->authenticatedLayout();

        $this->assertStringContainsString(
            'href="#main-content"',
            $layout,
            'A11Y-KBD-1: AuthenticatedLayout must render a skip-link targeting #main-content.',
        );
        $this->assertStringContainsString(
            'Skip to main content',
            $layout,
            'A11Y-KBD-1: the skip-link must carry visible (when focused) text.',
        );
        $this->assertStringContainsString(
            'sr-only',
            $layout,
            'A11Y-KBD-1: the skip-link must be visually hidden until focused (sr-only).',
        );
        $this->assertStringContainsString(
            'focus:not-sr-only',
            $layout,
            'A11Y-KBD-1: the skip-link must reveal itself on focus (focus:not-sr-only).',
        );
    }

    public function test_main_landmark_is_focus_targetable(): void
    {
        $layout = $this->authenticatedLayout();

        $this->assertMatchesRegularExpression(
            '/<main\s+id="main-content"\s+tabindex="-1"/',
            $layout,
            'A11Y-KBD-1: <main> must carry id="main-content" + tabindex="-1" so the skip-link can move focus to it.',
        );
    }
}
