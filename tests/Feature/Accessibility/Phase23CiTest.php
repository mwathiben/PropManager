<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-CI-1: accessibility lint-gate watchdog. Pins that the
 * eslint a11y plugin is installed + configured and that CI actually
 * runs `npm run lint` — so the gate cannot be silently removed.
 */
class Phase23CiTest extends TestCase
{
    public function test_eslint_a11y_plugin_is_configured(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey(
            'eslint-plugin-vuejs-accessibility',
            $package['devDependencies'] ?? [],
            'A11Y-CI-1: eslint-plugin-vuejs-accessibility must be a devDependency.',
        );
        $this->assertArrayHasKey(
            'eslint',
            $package['devDependencies'] ?? [],
            'A11Y-CI-1: eslint must be a devDependency.',
        );
        $this->assertSame(
            'eslint resources/js',
            $package['scripts']['lint'] ?? null,
            'A11Y-CI-1: package.json must expose a `lint` script.',
        );

        $configPath = base_path('eslint.config.js');
        $this->assertFileExists($configPath, 'A11Y-CI-1: eslint.config.js must exist.');

        $config = file_get_contents($configPath);
        $this->assertStringContainsString(
            'eslint-plugin-vuejs-accessibility',
            $config,
            'A11Y-CI-1: eslint.config.js must wire the vuejs-accessibility plugin.',
        );
        $this->assertStringContainsString(
            'configs.recommended.rules',
            $config,
            'A11Y-CI-1: eslint.config.js must apply the plugin recommended ruleset.',
        );
    }

    public function test_ci_runs_a11y_lint(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString(
            'npm run lint',
            $ci,
            'A11Y-CI-1: the CI workflow must run `npm run lint`.',
        );
        $this->assertStringContainsString(
            'Accessibility lint (ESLint)',
            $ci,
            'A11Y-CI-1: the CI workflow must have a named accessibility-lint step.',
        );
    }

    public function test_axe_smoke_harness_exists(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey(
            '@axe-core/playwright',
            $package['devDependencies'] ?? [],
            'A11Y-CI-2: @axe-core/playwright must be a devDependency.',
        );
        $this->assertArrayHasKey(
            '@playwright/test',
            $package['devDependencies'] ?? [],
            'A11Y-CI-2: @playwright/test must be a devDependency.',
        );
        $this->assertSame(
            'playwright test',
            $package['scripts']['test:a11y'] ?? null,
            'A11Y-CI-2: package.json must expose a `test:a11y` script.',
        );

        $this->assertFileExists(
            base_path('playwright.config.ts'),
            'A11Y-CI-2: playwright.config.ts must exist.',
        );

        $specPath = base_path('tests/a11y/axe-smoke.spec.ts');
        $this->assertFileExists($specPath, 'A11Y-CI-2: tests/a11y/axe-smoke.spec.ts must exist.');

        $spec = file_get_contents($specPath);
        $this->assertStringContainsString(
            'AxeBuilder',
            $spec,
            'A11Y-CI-2: the smoke spec must run axe-core via AxeBuilder.',
        );
        $this->assertStringContainsString(
            "GATED_IMPACTS = new Set(['critical'])",
            $spec,
            'A11Y-CI-2: the smoke spec must gate on critical violations (serious is the shrink-only baseline).',
        );
    }

    public function test_ci_has_a11y_smoke_job(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString(
            'a11y-smoke:',
            $ci,
            'A11Y-CI-2: the CI workflow must define an a11y-smoke job.',
        );
        $this->assertStringContainsString(
            'npm run test:a11y',
            $ci,
            'A11Y-CI-2: the a11y-smoke job must run `npm run test:a11y`.',
        );
    }
}
