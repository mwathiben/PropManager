<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-62 OFFLINE-WRITES-1/2/3 watchdog: registerOfflinePost helper,
 * 4 named queues, useBackgroundSync routeFamily mapping, persistent
 * IndexedDB write queue with dead-letter handling.
 */
class Phase62OfflineWritesTest extends TestCase
{
    public function test_sw_exposes_register_offline_post_helper(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            'function registerOfflinePost',
            $src,
            'OFFLINE-WRITES-1: sw.ts must define a registerOfflinePost helper that wraps Workbox BackgroundSyncPlugin so additional POST surfaces can opt in with one call.',
        );
    }

    public function test_sw_registers_four_named_offline_queues_in_addition_to_invoices(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        foreach (['pm-invoice-queue', 'pm-offline-tickets', 'pm-offline-comments', 'pm-offline-readings', 'pm-offline-payments'] as $queue) {
            $this->assertStringContainsString(
                "'{$queue}'",
                $src,
                "OFFLINE-WRITES-1: sw.ts must register the {$queue} BackgroundSync queue so the client + SW agree on the queue identity.",
            );
        }
    }

    public function test_sw_offline_post_matchers_cover_target_routes(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString(
            "url.pathname === '/tickets'",
            $src,
            'OFFLINE-WRITES-2: sw.ts must match POST /tickets exactly so /tickets/{id}/comment routes to its own queue.',
        );
        $this->assertStringContainsString(
            '/^\\/tickets\\/\\d+\\/comment$/',
            $src,
            'OFFLINE-WRITES-2: sw.ts must match POST /tickets/{id}/comment via regex on the comments queue.',
        );
        $this->assertStringContainsString(
            "url.pathname === '/readings'",
            $src,
            'OFFLINE-WRITES-2: sw.ts must match POST /readings on the readings queue.',
        );
        $this->assertStringContainsString(
            "url.pathname === '/payments/record'",
            $src,
            'OFFLINE-WRITES-2: sw.ts must match POST /payments/record on the payments queue.',
        );
    }

    public function test_use_background_sync_supports_route_family_option(): void
    {
        $src = (string) file_get_contents(resource_path('js/composables/useBackgroundSync.ts'));

        $this->assertStringContainsString(
            'RouteFamily',
            $src,
            'OFFLINE-WRITES-2: useBackgroundSync must export a RouteFamily type so callers can opt into the per-family queue.',
        );
        $this->assertStringContainsString(
            'QUEUE_NAMES',
            $src,
            'OFFLINE-WRITES-2: useBackgroundSync must map RouteFamily to queue names via a QUEUE_NAMES record.',
        );
        foreach (['invoices', 'tickets', 'comments', 'readings', 'payments'] as $family) {
            $this->assertStringContainsString(
                "'{$family}'",
                $src,
                "OFFLINE-WRITES-2: useBackgroundSync must accept routeFamily '{$family}'.",
            );
        }
        $this->assertStringContainsString(
            'enqueueOfflineWrite',
            $src,
            'OFFLINE-WRITES-3: useBackgroundSync must persist the queued payload via enqueueOfflineWrite so reopened tabs see pending work.',
        );
    }

    public function test_offline_write_queue_module_exists(): void
    {
        $path = resource_path('js/lib/offlineWriteQueue.ts');
        $this->assertFileExists($path, 'OFFLINE-WRITES-3: offlineWriteQueue.ts must exist (persistent IDB queue + dead-letter).');

        $src = (string) file_get_contents($path);
        foreach (['enqueueOfflineWrite', 'listPending', 'listDeadLetter', 'listReplayLog', 'recordReplayAttempt', 'recordReplaySuccess', 'discardOfflineWrite'] as $api) {
            $this->assertStringContainsString(
                $api,
                $src,
                "OFFLINE-WRITES-3: offlineWriteQueue must expose {$api}.",
            );
        }
        $this->assertStringContainsString(
            'MAX_ATTEMPTS',
            $src,
            'OFFLINE-WRITES-3: offlineWriteQueue must define a MAX_ATTEMPTS constant so dead-letter eviction is testable.',
        );
        // Each store gets its OWN database: idb-keyval's createStore() only
        // creates its object store during that db's v1 upgrade, so three
        // createStore() calls sharing one db name leave dead-letter/replay-log
        // uncreated (NotFoundError on boot). Dedicated dbs per store fix that.
        $this->assertStringContainsString(
            "createStore('pm-offline-writes-queue', 'queue')",
            $src,
            'OFFLINE-WRITES-3: offlineWriteQueue must use a dedicated pm-offline-writes-queue IDB database so it does not collide with the per-user offlineStore.',
        );
        $this->assertStringContainsString(
            "createStore('pm-offline-writes-dead-letter', 'dead-letter')",
            $src,
            'OFFLINE-WRITES-3: offlineWriteQueue must keep dead-letter ops in their own database so they survive queue drain.',
        );
        $this->assertStringContainsString(
            "createStore('pm-offline-writes-replay-log', 'replay-log')",
            $src,
            'OFFLINE-WRITES-3: offlineWriteQueue must keep a replay log for audit.',
        );
    }

    public function test_queued_ops_store_supports_route_family_and_dead_letter(): void
    {
        $src = (string) file_get_contents(resource_path('js/stores/queuedOps.ts'));

        $this->assertStringContainsString(
            'routeFamily',
            $src,
            'OFFLINE-WRITES-2: queuedOps store must carry the routeFamily tag so PendingSyncBadge can filter.',
        );
        $this->assertStringContainsString(
            'hasPendingFor',
            $src,
            'CONNECTIVITY-UX-2: queuedOps store must expose hasPendingFor(routeFamily, resourceId) for per-resource indicators.',
        );
        $this->assertStringContainsString(
            'markDeadLetter',
            $src,
            'OFFLINE-WRITES-3: queuedOps store must support markDeadLetter so the tray can render a Permanently failed section.',
        );
        $this->assertStringContainsString(
            'deadLetterCount',
            $src,
            'OFFLINE-WRITES-3: queuedOps store must expose deadLetterCount so the tray can show the failure count separately.',
        );
    }

    public function test_app_js_hydrates_queue_from_idb_on_boot(): void
    {
        $src = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            'offlineWriteQueue',
            $src,
            'OFFLINE-WRITES-3: app.js must import offlineWriteQueue so the boot hook can hydrate the Pinia store from IDB.',
        );
        $this->assertStringContainsString(
            'listPending',
            $src,
            'OFFLINE-WRITES-3: app.js must call listPending() on boot to surface pre-existing queued writes after a tab restart.',
        );
        $this->assertStringContainsString(
            'recordReplaySuccess',
            $src,
            'OFFLINE-WRITES-3: app.js must call recordReplaySuccess on BG_SYNC_DRAINED so the persistent queue stays in sync with Workbox.',
        );
    }
}
