<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-62 CACHE-STRATEGY-1/2/3 watchdog: per-route-family
 * runtimeCaching, shell precache, cache-invalidation hooks on mutation
 * success.
 */
class Phase62CacheStrategyTest extends TestCase
{
    public function test_sw_splits_api_reads_into_four_per_family_caches(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        foreach (['pm-api-dashboard', 'pm-api-static-lookups', 'pm-api-detail', 'pm-api-list'] as $cacheName) {
            $this->assertStringContainsString(
                "'{$cacheName}'",
                $src,
                "CACHE-STRATEGY-1: sw.ts must register a {$cacheName} cache to replace the legacy 'pm-api-reads' blanket strategy.",
            );
        }
    }

    public function test_sw_dashboard_uses_network_first_30s(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            "url.pathname === '/dashboard'",
            $src,
            'CACHE-STRATEGY-1: /dashboard route must be matched explicitly so it gets the NetworkFirst-30s strategy instead of bucket-defaulting to SWR-5min.',
        );
        // Look for a 30-second maxAgeSeconds near the dashboard cache.
        $this->assertMatchesRegularExpression(
            '/pm-api-dashboard.*?maxAgeSeconds:\s*30\b/s',
            $src,
            'CACHE-STRATEGY-1: dashboard cache must use maxAgeSeconds: 30 (NetworkFirst with a 30s TTL).',
        );
    }

    public function test_sw_static_lookups_use_cache_first_seven_days(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            '(currencies|plans|countries)',
            $src,
            'CACHE-STRATEGY-1: static-lookup routes must be matched by regex covering currencies / plans / countries.',
        );
        $this->assertMatchesRegularExpression(
            '/pm-api-static-lookups.*?maxAgeSeconds:\s*7\s*\*\s*24\s*\*\s*60\s*\*\s*60\b/s',
            $src,
            'CACHE-STRATEGY-1: static-lookup cache must use maxAgeSeconds: 7 * 24 * 60 * 60 (7d).',
        );
    }

    public function test_sw_detail_pages_use_swr_two_minutes(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            '(invoices|tickets|leases|payments|readings|properties|units)',
            $src,
            'CACHE-STRATEGY-1: detail-page regex must cover the resource list (invoices / tickets / leases / payments / readings / properties / units).',
        );
        $this->assertMatchesRegularExpression(
            '/pm-api-detail.*?maxAgeSeconds:\s*2\s*\*\s*60\b/s',
            $src,
            'CACHE-STRATEGY-1: detail-page cache must use maxAgeSeconds: 2 * 60 (2min).',
        );
    }

    public function test_sw_promotes_navigation_to_shell_cache(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            "'pm-shell-v1'",
            $src,
            'CACHE-STRATEGY-2: navigation cache must be renamed to pm-shell-v1 to signal it is the offline-shell cache.',
        );
        $this->assertMatchesRegularExpression(
            '/pm-shell-v1.*?maxAgeSeconds:\s*7\s*\*\s*24\s*\*\s*60\s*\*\s*60\b/s',
            $src,
            'CACHE-STRATEGY-2: shell cache must keep entries for 7d so a tab opened offline still has the AuthenticatedLayout.',
        );
    }

    public function test_sw_precaches_dashboard_at_install(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            "cache.add('/dashboard')",
            $src,
            'CACHE-STRATEGY-2: install handler must precache /dashboard so the first offline navigation has a cached shell.',
        );
    }

    public function test_sw_handles_cache_bust_and_sync_now_messages(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            "'CACHE_BUST'",
            $src,
            'CACHE-STRATEGY-3: sw.ts must handle CACHE_BUST messages.',
        );
        $this->assertStringContainsString(
            'bustCachesForFamily',
            $src,
            'CACHE-STRATEGY-3: sw.ts must implement bustCachesForFamily to invalidate matching SWR caches per route family.',
        );
        $this->assertStringContainsString(
            'ROUTE_FAMILY_TO_CACHES',
            $src,
            'CACHE-STRATEGY-3: sw.ts must define the ROUTE_FAMILY_TO_CACHES map so CACHE_BUST knows which caches to invalidate for each family.',
        );
        $this->assertStringContainsString(
            "'SYNC_NOW'",
            $src,
            'CONNECTIVITY-UX-3: sw.ts must handle SYNC_NOW messages (manual replay trigger).',
        );
    }

    public function test_app_js_posts_cache_bust_after_bg_sync_drained(): void
    {
        $src = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "type: 'CACHE_BUST'",
            $src,
            'CACHE-STRATEGY-3: app.js must postMessage CACHE_BUST to the SW after BG_SYNC_DRAINED so list pages auto-revalidate.',
        );
        $this->assertStringContainsString(
            "routeFamily",
            $src,
            'CACHE-STRATEGY-3: app.js must include routeFamily in the CACHE_BUST payload.',
        );
    }
}
