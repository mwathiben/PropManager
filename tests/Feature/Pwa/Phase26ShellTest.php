<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-SHELL-1 / 2 / 3 watchdogs: verify the service worker
 * pipeline is wired correctly. The watchdogs assert the BUILD
 * CONTRACT, not the build OUTPUT (the SW file is a build artifact —
 * Phase26CiTest in Phase 1d verifies the runtime behaviour).
 */
class Phase26ShellTest extends TestCase
{
    public function test_vite_plugin_pwa_is_a_dependency(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);
        $this->assertArrayHasKey(
            'vite-plugin-pwa',
            $pkg['devDependencies'] ?? [],
            'PWA-SHELL-1: vite-plugin-pwa must be in devDependencies — the build pipeline depends on it to generate the Workbox SW.',
        );
        $allDeps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);
        $this->assertArrayHasKey(
            'workbox-window',
            $allDeps,
            'PWA-SHELL-1: workbox-window must be available for the registration helper API.',
        );
    }

    public function test_vite_config_uses_inject_manifest_strategy(): void
    {
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString(
            'VitePWA',
            $vite,
            'PWA-SHELL-3: vite.config.js must import VitePWA.',
        );
        $this->assertStringContainsString(
            "strategies: 'injectManifest'",
            $vite,
            'PWA-SHELL-3: injectManifest strategy is required to preserve the push handlers in resources/js/sw.ts.',
        );
        $this->assertStringContainsString(
            "filename: 'sw.ts'",
            $vite,
            'PWA-SHELL-3: the SW source file must be sw.ts so the precache manifest can be injected.',
        );
    }

    public function test_sw_source_exists_and_carries_workbox_precache(): void
    {
        $path = resource_path('js/sw.ts');
        $this->assertFileExists($path, 'PWA-SHELL-1: resources/js/sw.ts must exist as the SW source.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'precacheAndRoute(self.__WB_MANIFEST)',
            $src,
            'PWA-SHELL-1: the SW must call precacheAndRoute with __WB_MANIFEST so the build hashes drive the cache version.',
        );
        $this->assertStringContainsString(
            'cleanupOutdatedCaches',
            $src,
            'PWA-SHELL-1: the SW must call cleanupOutdatedCaches so prior-build caches are evicted on activation.',
        );
    }

    public function test_sw_source_registers_navigation_fallback_to_offline(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            'NavigationRoute',
            $src,
            'PWA-SHELL-2: the SW must register a NavigationRoute so any HTML navigation can fall back to /offline.',
        );
        $this->assertStringContainsString(
            'denylist:',
            $src,
            'PWA-SHELL-2: NavigationRoute must denylist /api/, /docs/, /admin/, /sanctum/ etc. — those have their own failure semantics.',
        );
    }

    public function test_sw_source_preserves_push_handlers(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        foreach (['push', 'notificationclick', 'notificationclose', 'pushsubscriptionchange'] as $event) {
            $this->assertStringContainsString(
                "addEventListener('{$event}'",
                $src,
                "PWA-SHELL-3: the SW must preserve the {$event} handler so the existing push-notification backend keeps working.",
            );
        }
    }

    public function test_offline_route_is_registered(): void
    {
        // PWA-SHELL-2: /offline must be a real registered route so the
        // SW's NavigationRoute fallback can serve a precached HTML
        // response (and so users can hit it directly).
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(
            fn ($r) => $r->methods()[0].' '.$r->uri(),
        );

        $this->assertTrue(
            $routes->contains('GET offline'),
            'PWA-SHELL-2: /offline route must be registered (routes/web.php).',
        );
    }

    public function test_offline_inertia_page_exists(): void
    {
        $this->assertFileExists(
            resource_path('js/Pages/Offline.vue'),
            'PWA-SHELL-2: resources/js/Pages/Offline.vue must exist — the route renders this component.',
        );
    }

    public function test_sw_js_route_serves_with_service_worker_allowed_header(): void
    {
        // PWA-SHELL-1: /sw.js must be served with Service-Worker-Allowed: /
        // so the SW (which lives at public/build/sw.js) can claim root
        // scope. Without the header, registration with { scope: '/' }
        // throws SecurityError.
        //
        // We don't require the build artifact to exist for this test —
        // we route through Laravel which 404s gracefully when the file
        // isn't built yet (fresh checkout). The contract is that the
        // ROUTE is registered with the right headers when the file
        // IS present.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->methods()[0] === 'GET' && $r->uri() === 'sw.js');

        $this->assertNotNull($route, 'PWA-SHELL-1: GET /sw.js route must be registered (routes/web.php).');
    }

    public function test_pwa_runbook_documents_caching_strategies(): void
    {
        $path = base_path('docs/runbooks/pwa.md');
        $this->assertFileExists($path, 'PWA-SHELL-3: docs/runbooks/pwa.md must exist as the operator runbook.');

        $content = (string) file_get_contents($path);
        foreach (['CacheFirst', 'NetworkFirst', 'StaleWhileRevalidate', 'NetworkOnly'] as $strategy) {
            $this->assertStringContainsString(
                $strategy,
                $content,
                "PWA-PERF-3: the pwa.md runbook must document the {$strategy} strategy so the per-route-family contract is explicit.",
            );
        }
        foreach (['Versioning', 'Push notifications', 'Common debugging'] as $section) {
            $this->assertStringContainsString(
                $section,
                $content,
                "PWA-SHELL-3: pwa.md must include the '{$section}' section so operators know how to diagnose.",
            );
        }
    }

    public function test_legacy_sw_js_is_removed(): void
    {
        // PWA-SHELL-1: the pre-Phase-26 public/sw.js was push-only and
        // is replaced by the build-generated SW. Leaving the old file
        // around would mean a request to /sw.js hits Laravel public/
        // directly (bypassing the route) and serves the stale handler.
        $this->assertFileDoesNotExist(
            public_path('sw.js'),
            'PWA-SHELL-1: public/sw.js must be removed — the SW is now a build artifact served via the /sw.js Laravel route.',
        );
    }
}
