<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-62 CI-1: cross-category presence map for the PWA-OFFLINE-DEPTH
 * cycle. Locks in every surface added by Phase 62 so a future refactor
 * that accidentally drops one of the layers fails CI loudly.
 *
 * Per-category behavioural assertions live in the dedicated
 * Phase62OfflineWritesTest / Phase62OfflinePhotosTest /
 * Phase62CacheStrategyTest / Phase62ConflictResolutionTest /
 * Phase62ConnectivityUxTest. This test is a presence-only map.
 */
class Phase62PwaOfflineDepthSurfaceTest extends TestCase
{
    public function test_sw_exposes_register_offline_post_helper_and_four_named_queues(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString('function registerOfflinePost', $src);
        foreach (['pm-invoice-queue', 'pm-offline-tickets', 'pm-offline-comments', 'pm-offline-readings', 'pm-offline-payments'] as $queue) {
            $this->assertStringContainsString("'{$queue}'", $src);
        }
    }

    public function test_offline_write_queue_and_photo_store_modules_exist(): void
    {
        $this->assertFileExists(resource_path('js/lib/offlineWriteQueue.ts'));
        $this->assertFileExists(resource_path('js/lib/offlinePhotoStore.ts'));
    }

    public function test_version_column_exists_on_three_mutable_resources(): void
    {
        foreach (['tickets', 'ticket_comments', 'water_readings'] as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'version'),
                "CONFLICT-RESOLUTION-1: {$table} must have a version column.",
            );
        }
    }

    public function test_row_version_trait_and_write_conflict_exception_exist(): void
    {
        $this->assertTrue(trait_exists(\App\Models\Concerns\RowVersion::class));
        $this->assertTrue(class_exists(\App\Exceptions\WriteConflictException::class));
    }

    public function test_offline_ui_components_exist(): void
    {
        foreach ([
            'js/Components/Layout/SlowNetworkBanner.vue',
            'js/Components/Offline/PendingSyncBadge.vue',
            'js/Components/Offline/ConflictDialog.vue',
            'js/Components/Offline/PhotoUploadStatusList.vue',
        ] as $component) {
            $this->assertFileExists(resource_path($component));
        }
    }

    public function test_authenticated_layout_mounts_slow_network_banner_and_queued_ops_tray(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString('SlowNetworkBanner', $layout);
        $this->assertStringContainsString('QueuedOpsTray', $layout);
    }

    public function test_use_background_sync_supports_route_family_and_persists_to_idb(): void
    {
        $src = (string) file_get_contents(resource_path('js/composables/useBackgroundSync.ts'));

        $this->assertStringContainsString('RouteFamily', $src);
        $this->assertStringContainsString('QUEUE_NAMES', $src);
        $this->assertStringContainsString('enqueueOfflineWrite', $src);
    }

    public function test_sw_handles_cache_bust_and_sync_now_messages(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        $this->assertStringContainsString("'CACHE_BUST'", $src);
        $this->assertStringContainsString("'SYNC_NOW'", $src);
        $this->assertStringContainsString('bustCachesForFamily', $src);
    }

    public function test_per_route_family_caches_registered(): void
    {
        $src = (string) file_get_contents(resource_path('js/sw.ts'));

        foreach (['pm-shell-v1', 'pm-api-dashboard', 'pm-api-static-lookups', 'pm-api-detail', 'pm-api-list'] as $cacheName) {
            $this->assertStringContainsString("'{$cacheName}'", $src);
        }
    }

    public function test_offline_md_runbook_documents_phase_62_surface(): void
    {
        $path = base_path('docs/runbooks/offline.md');
        $this->assertFileExists($path, 'CI-3: docs/runbooks/offline.md must exist (Phase 62 runbook).');

        $src = (string) file_get_contents($path);
        foreach ([
            'Phase 62',
            'Queue lifecycle',
            'pm-offline-tickets',
            'pm-offline-comments',
            'pm-offline-readings',
            'pm-offline-payments',
            'CACHE_BUST',
            'WriteConflictException',
            'PHOTO_BUDGET_BYTES',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $src,
                "CI-3: offline.md must mention {$token} so the runbook tells the operator/dev what each surface is.",
            );
        }
    }

    public function test_alert_thresholds_includes_phase_62_gauges(): void
    {
        $src = (string) file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));

        foreach (['offline_writes_dead_letter_count', 'offline_photo_quota_evictions_count', 'offline_shell_boot_count'] as $gauge) {
            $this->assertStringContainsString(
                $gauge,
                $src,
                "CI-3: alert-thresholds.md must list the {$gauge} gauge so it is visible to the operator.",
            );
        }
    }
}
