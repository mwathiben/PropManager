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
}
