<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-CI-1 / 2 / 3 watchdogs: assert the LHCI + Playwright
 * gate infrastructure is wired. The actual gate behaviour runs in
 * GitHub Actions (Node + Chromium); these PHP-level checks make sure
 * a future edit cannot silently remove the gate (delete the spec,
 * drop the workflow job, lower the LHCI minScore) without failing
 * PHPUnit.
 */
class Phase26CiTest extends TestCase
{
    public function test_lighthouserc_exists_with_pwa_score_gate(): void
    {
        $path = base_path('lighthouserc.cjs');
        $this->assertFileExists($path, 'PWA-CI-1: lighthouserc.cjs must exist as the Lighthouse CI configuration.');

        $config = (string) file_get_contents($path);

        $this->assertStringContainsString(
            "'categories:pwa'",
            $config,
            'PWA-CI-1: lighthouserc.cjs must declare a categories:pwa assertion.',
        );
        $this->assertMatchesRegularExpression(
            "/minScore:\s*0\.9/",
            $config,
            'PWA-CI-1: the PWA score gate must be 0.9 — lowering it without raising the bar elsewhere weakens the gate silently.',
        );
        $this->assertStringContainsString(
            "'error'",
            $config,
            'PWA-CI-1: the assertion level for categories:pwa must be "error" so a failed audit fails the build (not "warn" which only logs).',
        );
    }

    public function test_lhci_puppeteer_login_script_exists(): void
    {
        $path = base_path('scripts/lhci-puppeteer-login.cjs');
        $this->assertFileExists(
            $path,
            'PWA-CI-1: scripts/lhci-puppeteer-login.cjs must exist — Lighthouse needs to log in as the seeded landlord before auditing /dashboard.',
        );

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            '/login',
            $src,
            'PWA-CI-1: the puppeteer script must navigate to /login before submitting the form.',
        );
    }

    public function test_pwa_sw_playwright_spec_exists(): void
    {
        $path = base_path('tests/pwa/sw.spec.ts');
        $this->assertFileExists(
            $path,
            'PWA-CI-2: tests/pwa/sw.spec.ts must exist — service-worker integration test.',
        );

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'navigator.serviceWorker.controller',
            $src,
            'PWA-CI-2: the spec must assert navigator.serviceWorker.controller is non-null after registration.',
        );
        $this->assertStringContainsString(
            'setOffline(true)',
            $src,
            'PWA-CI-2: the spec must force offline mode to verify the navigation fallback to /offline.',
        );
        $this->assertStringContainsString(
            "You're offline",
            $src,
            'PWA-CI-2: the spec must assert the /offline page actually renders (not the Chrome dinosaur).',
        );
    }

    public function test_pwa_install_playwright_spec_exists(): void
    {
        $path = base_path('tests/pwa/install.spec.ts');
        $this->assertFileExists(
            $path,
            'PWA-CI-3: tests/pwa/install.spec.ts must exist — install-prompt regression test.',
        );

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            "'/manifest.json'",
            $src,
            'PWA-CI-3: the spec must fetch /manifest.json and verify it parses + declares icons.',
        );
        $this->assertStringContainsString(
            "'192x192'",
            $src,
            'PWA-CI-3: the spec must verify the manifest declares a 192x192 icon (Chrome installability requirement).',
        );
        $this->assertStringContainsString(
            "'512x512'",
            $src,
            'PWA-CI-3: the spec must verify the manifest declares a 512x512 icon (Chrome installability requirement).',
        );
        $this->assertStringContainsString(
            'service-worker-allowed',
            $src,
            'PWA-CI-3: the spec must verify /sw.js carries Service-Worker-Allowed: / so the SW gets root scope.',
        );
    }

    public function test_playwright_pwa_config_exists(): void
    {
        $path = base_path('playwright.pwa.config.ts');
        $this->assertFileExists(
            $path,
            'PWA-CI-2/3: playwright.pwa.config.ts must exist so the PWA specs run in their own Playwright context.',
        );

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            "testDir: './tests/pwa'",
            $src,
            'PWA-CI-2/3: the config must point at tests/pwa.',
        );
    }

    public function test_package_scripts_expose_pwa_runners(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey(
            'test:pwa',
            $pkg['scripts'] ?? [],
            'PWA-CI-2/3: package.json must expose "test:pwa" so CI + operators have a single command for the PWA Playwright suite.',
        );
        $this->assertArrayHasKey(
            'test:lhci',
            $pkg['scripts'] ?? [],
            'PWA-CI-1: package.json must expose "test:lhci" so CI + operators have a single command for the Lighthouse PWA gate.',
        );
    }

    public function test_lhci_cli_is_a_dependency(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);
        $allDeps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);
        $this->assertArrayHasKey(
            '@lhci/cli',
            $allDeps,
            'PWA-CI-1: @lhci/cli must be in devDependencies for the Lighthouse PWA gate.',
        );
    }

    public function test_github_workflow_includes_lighthouse_and_pwa_jobs(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString(
            'lighthouse-pwa:',
            $workflow,
            'PWA-CI-1: .github/workflows/ci.yml must define the lighthouse-pwa job — otherwise the gate never runs.',
        );
        $this->assertStringContainsString(
            'pwa-smoke:',
            $workflow,
            'PWA-CI-2/3: .github/workflows/ci.yml must define the pwa-smoke job — otherwise the SW + install specs never run.',
        );
        $this->assertStringContainsString(
            'npm run test:lhci',
            $workflow,
            'PWA-CI-1: the lighthouse-pwa job must invoke npm run test:lhci.',
        );
        $this->assertStringContainsString(
            'npm run test:pwa',
            $workflow,
            'PWA-CI-2/3: the pwa-smoke job must invoke npm run test:pwa.',
        );
    }
}
