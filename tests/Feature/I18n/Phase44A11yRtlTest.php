<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-44 A11Y-RTL-3 (watchdog complement): source-level assertions
 * for the useRtlAware composable + its barrel export. The composable
 * fulfils A11Y-RTL-1 (arrow-key inversion) + A11Y-RTL-2 (aria-live
 * direction-change announcement); the watchdog guards against a future
 * refactor silently dropping either capability.
 *
 * Same source-grep watchdog pattern as Phase-23 a11y / Phase-43 i18n /
 * Phase-44 ESLINT-CUSTOM-3.
 */
class Phase44A11yRtlTest extends TestCase
{
    public function test_use_rtl_aware_composable_exists(): void
    {
        $path = resource_path('js/composables/useRtlAware.ts');
        $this->assertFileExists(
            $path,
            'A11Y-RTL-3: useRtlAware composable must exist (arrow-key inversion + direction announcement).',
        );
    }

    public function test_use_rtl_aware_inverts_arrow_keys(): void
    {
        $src = file_get_contents(resource_path('js/composables/useRtlAware.ts'));

        foreach (['ArrowLeft', 'ArrowRight', 'forwardKey', 'backwardKey', 'isRtl'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $src,
                "A11Y-RTL-1: useRtlAware must reference {$needle} for arrow-key inversion.",
            );
        }
    }

    public function test_use_rtl_aware_announces_direction_changes(): void
    {
        $src = file_get_contents(resource_path('js/composables/useRtlAware.ts'));

        $this->assertStringContainsString(
            'useAnnouncer',
            $src,
            'A11Y-RTL-2: useRtlAware must pipe direction changes through useAnnouncer (aria-live).',
        );
        $this->assertStringContainsString(
            'announce(',
            $src,
            'A11Y-RTL-2: useRtlAware must call announce() on direction change.',
        );
        $this->assertStringContainsString(
            'watch(dir',
            $src,
            'A11Y-RTL-2: useRtlAware must watch dir to detect locale switches.',
        );
    }

    public function test_use_rtl_aware_is_exported_from_barrel(): void
    {
        $barrel = file_get_contents(resource_path('js/composables/index.ts'));

        $this->assertStringContainsString(
            'export { useRtlAware }',
            $barrel,
            'A11Y-RTL-3: useRtlAware must be re-exported from @/composables barrel.',
        );
    }
}
