<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-62 CONNECTIVITY-UX-1/2/3 watchdog: SlowNetworkBanner wires the
 * dormant useConnection.isSlow; PendingSyncBadge per-resource indicator;
 * 'Sync now' manual trigger.
 */
class Phase62ConnectivityUxTest extends TestCase
{
    public function test_slow_network_banner_component_exists(): void
    {
        $path = resource_path('js/Components/Layout/SlowNetworkBanner.vue');
        $this->assertFileExists($path, 'CONNECTIVITY-UX-1: SlowNetworkBanner.vue must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'useConnection',
            $src,
            'CONNECTIVITY-UX-1: SlowNetworkBanner must consume useConnection (wiring the dormant isSlow primitive).',
        );
        $this->assertStringContainsString(
            'isSlow',
            $src,
            'CONNECTIVITY-UX-1: SlowNetworkBanner must read useConnection.isSlow.',
        );
        $this->assertStringContainsString(
            "'connectivity.slow_banner'",
            $src,
            'CONNECTIVITY-UX-1: SlowNetworkBanner must render an i18n-keyed copy line, not hardcoded English.',
        );
        $this->assertStringContainsString(
            'pm.slow_banner.dismissed_until',
            $src,
            'CONNECTIVITY-UX-1: SlowNetworkBanner must persist its dismiss state to localStorage so frequent toggles do not nag.',
        );
        $this->assertStringContainsString(
            'role="status"',
            $src,
            'CONNECTIVITY-UX-1: SlowNetworkBanner must be role=status for screen-reader announcement.',
        );
    }

    public function test_authenticated_layout_mounts_slow_network_banner(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            'SlowNetworkBanner',
            $layout,
            'CONNECTIVITY-UX-1: AuthenticatedLayout must render <SlowNetworkBanner /> so the banner spans every authenticated page.',
        );
        $this->assertStringContainsString(
            "from '@/Components/Layout/SlowNetworkBanner.vue'",
            $layout,
            'CONNECTIVITY-UX-1: AuthenticatedLayout must import SlowNetworkBanner from Components/Layout/.',
        );
    }

    public function test_pending_sync_badge_component_exists(): void
    {
        $path = resource_path('js/Components/Offline/PendingSyncBadge.vue');
        $this->assertFileExists($path, 'CONNECTIVITY-UX-2: PendingSyncBadge.vue must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'hasPendingFor',
            $src,
            'CONNECTIVITY-UX-2: PendingSyncBadge must use the hasPendingFor selector to filter by (routeFamily, resourceId).',
        );
        $this->assertStringContainsString(
            'routeFamily',
            $src,
            'CONNECTIVITY-UX-2: PendingSyncBadge must accept routeFamily prop.',
        );
        $this->assertStringContainsString(
            'resourceId',
            $src,
            'CONNECTIVITY-UX-2: PendingSyncBadge must accept resourceId prop so per-row badges are scoped.',
        );
        $this->assertStringContainsString(
            "'connectivity.pending_sync'",
            $src,
            'CONNECTIVITY-UX-2: PendingSyncBadge must render an i18n-keyed copy line.',
        );
    }

    public function test_queued_ops_tray_has_sync_now_button(): void
    {
        $src = (string) file_get_contents(resource_path('js/Components/QueuedOpsTray.vue'));

        $this->assertStringContainsString(
            "type: 'SYNC_NOW'",
            $src,
            'CONNECTIVITY-UX-3: QueuedOpsTray must post a SYNC_NOW message to the active SW.',
        );
        $this->assertStringContainsString(
            "'connectivity.sync_now'",
            $src,
            'CONNECTIVITY-UX-3: QueuedOpsTray must render the sync_now i18n-keyed label.',
        );
        $this->assertStringContainsString(
            'queued-ops-sync-now',
            $src,
            'CONNECTIVITY-UX-3: QueuedOpsTray must expose data-testid="queued-ops-sync-now" so Playwright/Vitest can target it.',
        );
    }

    public function test_connectivity_i18n_keys_exist_in_en_sw_ar(): void
    {
        $required = ['slow_banner', 'dismiss', 'sync_now', 'syncing', 'pending_sync'];

        foreach (['en', 'sw', 'ar'] as $locale) {
            $bundle = json_decode((string) file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('connectivity', $bundle, "lang/{$locale}.json must have connectivity namespace.");
            foreach ($required as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $bundle['connectivity'],
                    "CONNECTIVITY-UX-1/2/3: lang/{$locale}.json must include connectivity.{$key}.",
                );
            }
        }
    }
}
