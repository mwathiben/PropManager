<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-44 VISUAL-REGRESSION-3 (watchdog complement): source-level
 * assertions that the Phase-44 RTL visual-regression harness stays
 * wired up — the config file, the spec, and the package.json scripts.
 *
 * Same source-grep watchdog pattern as Phase-23 a11y harness,
 * Phase-26 PWA harness, Phase-43 codemod presence.
 */
class Phase44VisualRegressionTest extends TestCase
{
    public function test_rtl_playwright_config_exists_and_targets_rtl_dir(): void
    {
        $path = base_path('playwright.rtl.config.ts');
        $this->assertFileExists(
            $path,
            'VISUAL-REGRESSION-3: playwright.rtl.config.ts must exist (RTL snapshot harness).',
        );

        $contents = file_get_contents($path);
        $this->assertStringContainsString(
            "testDir: './tests/a11y/rtl'",
            $contents,
            'VISUAL-REGRESSION-3: rtl config must target tests/a11y/rtl/.',
        );
        $this->assertStringContainsString(
            "locale: 'ar'",
            $contents,
            'VISUAL-REGRESSION-3: rtl config must set the browser locale to ar.',
        );
        $this->assertStringContainsString(
            'maxDiffPixelRatio',
            $contents,
            'VISUAL-REGRESSION-3: rtl config must set a diff threshold for snapshot stability.',
        );
    }

    public function test_rtl_snapshot_spec_exists_and_covers_high_traffic_pages(): void
    {
        $path = base_path('tests/a11y/rtl/rtl-snapshot.spec.ts');
        $this->assertFileExists(
            $path,
            'VISUAL-REGRESSION-3: tests/a11y/rtl/rtl-snapshot.spec.ts must exist.',
        );

        $contents = file_get_contents($path);
        foreach ([
            '/dashboard',
            '/tenants',
            '/buildings',
            '/invoices',
            '/payments',
            '/profile',
        ] as $route) {
            $this->assertStringContainsString(
                "'{$route}'",
                $contents,
                "VISUAL-REGRESSION-3: rtl snapshot spec must cover {$route}.",
            );
        }
        $this->assertStringContainsString(
            'toHaveScreenshot',
            $contents,
            'VISUAL-REGRESSION-3: rtl spec must take Playwright snapshots.',
        );
    }

    public function test_package_json_exposes_test_rtl_scripts(): void
    {
        $pkg = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey('test:rtl', $pkg['scripts'] ?? [], 'VISUAL-REGRESSION-3: test:rtl script must be defined.');
        $this->assertArrayHasKey('test:rtl:update', $pkg['scripts'] ?? [], 'VISUAL-REGRESSION-3: test:rtl:update script must be defined.');

        $this->assertStringContainsString(
            'playwright.rtl.config.ts',
            $pkg['scripts']['test:rtl'],
            'VISUAL-REGRESSION-3: test:rtl must invoke playwright.rtl.config.ts.',
        );
        $this->assertStringContainsString(
            '--update-snapshots',
            $pkg['scripts']['test:rtl:update'],
            'VISUAL-REGRESSION-3: test:rtl:update must pass --update-snapshots.',
        );
    }
}
