<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-NETWORK-1 / 2 / 3 watchdogs: background-sync wiring,
 * Network Information API composable, queued-ops Pinia store + tray.
 * Behavioural assertions (offline POST flows, slow-2g chart deferral)
 * land in the Playwright suite — here we lock the source contract.
 */
class Phase26NetworkTest extends TestCase
{
    public function test_sw_registers_background_sync_for_invoice_posts(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            'BackgroundSyncPlugin',
            $src,
            'PWA-NETWORK-1: sw.ts must import BackgroundSyncPlugin from workbox-background-sync.',
        );
        $this->assertStringContainsString(
            "'pm-invoice-queue'",
            $src,
            'PWA-NETWORK-1: sw.ts must register a queue named pm-invoice-queue so the client + SW agree on the queue identity.',
        );
        $this->assertStringContainsString(
            "url.pathname.startsWith('/invoices')",
            $src,
            'PWA-NETWORK-1: bg-sync route must match POST /invoices.',
        );
        $this->assertStringContainsString(
            'BG_SYNC_DRAINED',
            $src,
            'PWA-NETWORK-1: the SW must postMessage BG_SYNC_DRAINED on successful replay so the host can clear the queue tray.',
        );
    }

    public function test_use_background_sync_composable_exists(): void
    {
        $path = resource_path('js/composables/useBackgroundSync.ts');
        $this->assertFileExists($path, 'PWA-NETWORK-1: useBackgroundSync composable must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'X-Idempotency-Key',
            $src,
            'PWA-NETWORK-1: the composable must attach X-Idempotency-Key (Phase-16 RESIL-3) so SW replays are safe.',
        );
        $this->assertStringContainsString(
            'QueuedOfflineError',
            $src,
            'PWA-NETWORK-1: the composable must throw QueuedOfflineError on network failure so callers can distinguish queued-for-replay from true 4xx/5xx.',
        );
        $this->assertStringContainsString(
            "from '@/stores/queuedOps'",
            $src,
            'PWA-NETWORK-1: the composable must add to the queuedOps store so the tray surfaces the queued op.',
        );
    }

    public function test_use_connection_composable_exists(): void
    {
        $path = resource_path('js/composables/useConnection.ts');
        $this->assertFileExists($path, 'PWA-NETWORK-2: useConnection composable must exist.');

        $src = (string) file_get_contents($path);
        foreach (['effectiveType', 'saveData', 'downlink', 'rtt', 'isSlow'] as $field) {
            $this->assertStringContainsString(
                $field,
                $src,
                "PWA-NETWORK-2: useConnection must expose `{$field}` (Network Information API surface).",
            );
        }
        $this->assertStringContainsString(
            "'slow-2g'",
            $src,
            'PWA-NETWORK-2: useConnection must treat slow-2g as the slow threshold.',
        );
        $this->assertStringContainsString(
            'navigator',
            $src,
            'PWA-NETWORK-2: useConnection must read from navigator.connection (with vendor-prefixed fallbacks).',
        );
    }

    public function test_use_connection_falls_back_safely_in_unsupported_browsers(): void
    {
        // PWA-NETWORK-2: Firefox + Safari don't ship the API. The
        // composable must return safe defaults so pages don't need
        // browser-detection. The source-level proxy here is that the
        // ref initialiser uses a `?? '4g'` (or equivalent fallback)
        // for effectiveType.
        $src = (string) file_get_contents(resource_path('js/composables/useConnection.ts'));
        $this->assertStringContainsString(
            "?? '4g'",
            $src,
            'PWA-NETWORK-2: useConnection must default effectiveType to "4g" when the API is absent (Firefox/Safari) so isSlow stays false.',
        );
    }

    public function test_queued_ops_pinia_store_exists(): void
    {
        $path = resource_path('js/stores/queuedOps.ts');
        $this->assertFileExists($path, 'PWA-NETWORK-3: queuedOps Pinia store must exist.');

        $src = (string) file_get_contents($path);
        foreach (['add', 'cancel', 'drain', 'count', 'hasPending'] as $api) {
            $this->assertStringContainsString(
                $api,
                $src,
                "PWA-NETWORK-3: queuedOps store must expose `{$api}` so the tray + SW message handler can interact with it.",
            );
        }
    }

    public function test_queued_ops_tray_component_exists(): void
    {
        $path = resource_path('js/Components/QueuedOpsTray.vue');
        $this->assertFileExists($path, 'PWA-NETWORK-3: QueuedOpsTray.vue must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'useQueuedOpsStore',
            $src,
            'PWA-NETWORK-3: the tray must read from the queuedOps Pinia store.',
        );
        $this->assertStringContainsString(
            'store.hasPending',
            $src,
            'PWA-NETWORK-3: the tray must hide itself when store.hasPending is false (silent-when-empty discipline).',
        );
    }

    public function test_authenticated_layout_renders_queued_ops_tray(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));
        $this->assertStringContainsString(
            'QueuedOpsTray',
            $layout,
            'PWA-NETWORK-3: AuthenticatedLayout must render <QueuedOpsTray /> so the tray spans every authenticated page.',
        );
    }

    public function test_app_js_routes_sw_bg_sync_drain_message_to_store(): void
    {
        $appJs = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString(
            'BG_SYNC_DRAINED',
            $appJs,
            'PWA-NETWORK-1+3: app.js must listen for BG_SYNC_DRAINED messages from the SW.',
        );
        $this->assertStringContainsString(
            'queuedOps',
            $appJs,
            'PWA-NETWORK-3: app.js must route the drain message to the queuedOps store.',
        );
    }

    public function test_offline_queue_i18n_keys_exist_in_both_locales(): void
    {
        foreach (['en', 'sw'] as $locale) {
            $bundle = json_decode((string) file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('offline', $bundle);
            $this->assertArrayHasKey('queue', $bundle['offline']);
            foreach (['title', 'aria', 'badge', 'collapse', 'queued_secs_ago', 'footer'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $bundle['offline']['queue'],
                    "PWA-NETWORK-3: lang/{$locale}.json must include offline.queue.{$key}.",
                );
            }
        }
    }
}
