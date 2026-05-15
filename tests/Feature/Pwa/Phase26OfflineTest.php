<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-OFFLINE-1 / 2 / 3 watchdogs: cached-fallback fetch
 * composable + IDB wrapper + topbar online indicator.
 *
 * The behavioural assertions (cached-value-served-on-fetch-failure)
 * live in the Playwright spec from Phase 1d — PHP unit tests can only
 * verify the source files are present and wired correctly. The
 * watchdog here is the source-of-truth audit, not the runtime test.
 */
class Phase26OfflineTest extends TestCase
{
    public function test_offline_store_wrapper_exists(): void
    {
        $path = resource_path('js/lib/offlineStore.ts');
        $this->assertFileExists($path, 'PWA-OFFLINE-2: resources/js/lib/offlineStore.ts must exist as the IDB wrapper.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            "from 'idb-keyval'",
            $src,
            'PWA-OFFLINE-2: offlineStore must wrap idb-keyval (raw IDB API is too verbose; engineers reach for localStorage instead).',
        );
    }

    public function test_offline_store_namespaces_keys_per_user(): void
    {
        $src = (string) file_get_contents(resource_path('js/lib/offlineStore.ts'));

        $this->assertStringContainsString(
            'configureOfflineStoreIdentity',
            $src,
            'PWA-OFFLINE-2: an identity resolver must be configurable so keys are namespaced by user (logout / impersonation safety).',
        );
        $this->assertStringContainsString(
            'pm:',
            $src,
            'PWA-OFFLINE-2: cached keys must carry a pm: prefix that includes userId + landlordId to prevent cross-account leak.',
        );
        $this->assertStringContainsString(
            'clearForCurrentUser',
            $src,
            'PWA-OFFLINE-2: clearForCurrentUser must be exported so logout flows can wipe the namespace.',
        );
    }

    public function test_offline_store_supports_ttl(): void
    {
        $src = (string) file_get_contents(resource_path('js/lib/offlineStore.ts'));

        $this->assertStringContainsString(
            'ttlMs',
            $src,
            'PWA-OFFLINE-2: per-entry TTL must be supported. Stale cached invoice totals are worse than no cache — landlord acts on outdated info.',
        );
        $this->assertStringContainsString(
            'cachedAt',
            $src,
            'PWA-OFFLINE-2: the envelope must record cachedAt so the UI can show a "cached N min ago" hint.',
        );
    }

    public function test_use_offline_data_composable_exists(): void
    {
        $path = resource_path('js/composables/useOfflineData.ts');
        $this->assertFileExists($path, 'PWA-OFFLINE-1: resources/js/composables/useOfflineData.ts must exist as the cached-fallback fetch composable.');

        $src = (string) file_get_contents($path);
        foreach (['data', 'isFresh', 'cachedAt', 'error', 'refresh'] as $field) {
            $this->assertStringContainsString(
                $field,
                $src,
                "PWA-OFFLINE-1: useOfflineData must expose `{$field}` so the UI can show fresh vs cached state.",
            );
        }
    }

    public function test_use_offline_data_reads_cache_first_then_fetches(): void
    {
        $src = (string) file_get_contents(resource_path('js/composables/useOfflineData.ts'));

        $this->assertStringContainsString(
            'getCached',
            $src,
            'PWA-OFFLINE-1: useOfflineData must call getCached BEFORE the fetch to avoid blank-flash on slow networks.',
        );
        $this->assertStringContainsString(
            'setCached',
            $src,
            'PWA-OFFLINE-1: useOfflineData must persist the fresh response via setCached so the next load survives offline.',
        );
    }

    public function test_online_indicator_component_exists(): void
    {
        $path = resource_path('js/Components/OnlineIndicator.vue');
        $this->assertFileExists($path, 'PWA-OFFLINE-3: resources/js/Components/OnlineIndicator.vue must exist as the topbar pill.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'navigator.onLine',
            $src,
            'PWA-OFFLINE-3: indicator must read navigator.onLine — that is the HTTP-layer signal (distinct from ConnectionStatus which tracks the WebSocket/Echo channel).',
        );
        $this->assertStringContainsString(
            "addEventListener('online'",
            $src,
            'PWA-OFFLINE-3: indicator must listen for online events so it updates reactively.',
        );
        $this->assertStringContainsString(
            "addEventListener('offline'",
            $src,
            'PWA-OFFLINE-3: indicator must listen for offline events.',
        );
    }

    public function test_authenticated_layout_renders_online_indicator(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            'OnlineIndicator',
            $layout,
            'PWA-OFFLINE-3: AuthenticatedLayout must render OnlineIndicator in the topbar.',
        );
    }

    public function test_offline_i18n_keys_exist_in_both_locales(): void
    {
        foreach (['en', 'sw'] as $locale) {
            $bundle = json_decode((string) file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('offline', $bundle, "PWA-OFFLINE-3: lang/{$locale}.json must include the offline namespace.");
            $this->assertArrayHasKey('indicator', $bundle['offline'], "PWA-OFFLINE-3: lang/{$locale}.json offline.indicator must exist.");
            foreach (['label', 'aria', 'tooltip'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $bundle['offline']['indicator'],
                    "PWA-OFFLINE-3: lang/{$locale}.json offline.indicator.{$key} must be present (Phase-24 parity contract).",
                );
                $this->assertNotEmpty(
                    $bundle['offline']['indicator'][$key],
                    "PWA-OFFLINE-3: lang/{$locale}.json offline.indicator.{$key} must not be empty.",
                );
            }
        }
    }

    public function test_idb_keyval_is_a_dependency(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);
        $allDeps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);
        $this->assertArrayHasKey(
            'idb-keyval',
            $allDeps,
            'PWA-OFFLINE-2: idb-keyval must be in dependencies — offlineStore.ts imports from it.',
        );
    }
}
