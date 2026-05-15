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
        // Phase-24 I18N-FRONT-3: the skip-link text now resolves via
        // vue-i18n; assert the binding + the key, instead of the literal.
        $this->assertStringContainsString(
            "t('nav.skip_to_main')",
            $layout,
            'A11Y-KBD-1 + I18N-FRONT-3: the skip-link must call t("nav.skip_to_main").',
        );
        foreach (['en', 'sw'] as $locale) {
            $bundle = json_decode(file_get_contents(lang_path("{$locale}.json")), true) ?: [];
            $this->assertNotSame(
                null,
                data_get($bundle, 'nav.skip_to_main'),
                "A11Y-KBD-1: lang/{$locale}.json must define nav.skip_to_main.",
            );
        }
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

    public function test_dropdown_manages_focus(): void
    {
        $dropdown = file_get_contents(resource_path('js/Components/Dropdown.vue'));

        $this->assertStringContainsString(
            'requestAnimationFrame',
            $dropdown,
            'A11Y-KBD-2: Dropdown must move focus into the menu on open.',
        );
        foreach (['ArrowDown', 'ArrowUp', 'Home', 'End'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $dropdown,
                "A11Y-KBD-2: Dropdown must handle the {$key} key for menu navigation.",
            );
        }
        $this->assertStringContainsString(
            'restoreFocusToTrigger',
            $dropdown,
            'A11Y-KBD-2: Dropdown must restore focus to the trigger on Escape.',
        );
        $this->assertStringContainsString(
            'useEscapeKey',
            $dropdown,
            'A11Y-KBD-2: Dropdown must wire Escape-to-close.',
        );
    }

    public function test_mobile_sidebar_is_a_trapped_modal(): void
    {
        $layout = $this->authenticatedLayout();

        $this->assertStringContainsString(
            'useFocusTrap(mobileSidebarRef',
            $layout,
            'A11Y-KBD-3: the mobile sidebar must apply a focus trap.',
        );
        $this->assertStringContainsString(
            'useBodyScrollLock(showMobileSidebar)',
            $layout,
            'A11Y-KBD-3: the mobile sidebar must lock body scroll while open.',
        );
        $this->assertMatchesRegularExpression(
            '/role="dialog"\s+aria-modal="true"/',
            $layout,
            'A11Y-KBD-3: the mobile sidebar overlay must carry role="dialog" + aria-modal="true".',
        );
        $this->assertStringContainsString(
            'closeMobileSidebar',
            $layout,
            'A11Y-KBD-3: the mobile sidebar must close (with focus restore) via closeMobileSidebar.',
        );
    }
}
