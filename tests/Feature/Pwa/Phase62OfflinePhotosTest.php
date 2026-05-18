<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-62 OFFLINE-PHOTOS-1/2/3 watchdog: FileReader→Blob→IndexedDB
 * pipeline for ticket annotations + per-photo retry UI + 50MB budget
 * with cleanup-on-success.
 */
class Phase62OfflinePhotosTest extends TestCase
{
    public function test_offline_photo_store_module_exists(): void
    {
        $path = resource_path('js/lib/offlinePhotoStore.ts');
        $this->assertFileExists($path, 'OFFLINE-PHOTOS-1: offlinePhotoStore.ts must exist.');

        $src = (string) file_get_contents($path);
        foreach (['enqueuePhoto', 'listPending', 'listPendingForTicket', 'markUploading', 'markFailed', 'discardPhoto', 'getTotalBytes', 'enforceBudget'] as $api) {
            $this->assertStringContainsString(
                $api,
                $src,
                "OFFLINE-PHOTOS-1: offlinePhotoStore must expose {$api}.",
            );
        }
        $this->assertStringContainsString(
            'PHOTO_BUDGET_BYTES',
            $src,
            'OFFLINE-PHOTOS-3: offlinePhotoStore must expose PHOTO_BUDGET_BYTES so the budget is testable.',
        );
        $this->assertStringContainsString(
            '50 * 1024 * 1024',
            $src,
            'OFFLINE-PHOTOS-3: offlinePhotoStore must default the budget to 50MB.',
        );
        $this->assertStringContainsString(
            "createStore('pm-offline-photos', 'photos')",
            $src,
            'OFFLINE-PHOTOS-1: offlinePhotoStore must use a dedicated pm-offline-photos IDB database (separate from pm-offline-writes).',
        );
        $this->assertStringContainsString(
            'PhotoQuotaExceededError',
            $src,
            'OFFLINE-PHOTOS-3: offlinePhotoStore must export PhotoQuotaExceededError so callers can fall back gracefully.',
        );
    }

    public function test_ticket_photo_annotator_persists_blob_before_upload(): void
    {
        $path = resource_path('js/Components/TicketPhotoAnnotator.vue');
        $this->assertFileExists($path, 'Phase-45 TicketPhotoAnnotator.vue must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            "from '@/lib/offlinePhotoStore'",
            $src,
            'OFFLINE-PHOTOS-1: TicketPhotoAnnotator must import the offline photo store.',
        );
        $this->assertStringContainsString(
            'enqueuePhoto',
            $src,
            'OFFLINE-PHOTOS-1: TicketPhotoAnnotator must call enqueuePhoto before the upload attempt so a network blip preserves the annotation.',
        );
        $this->assertStringContainsString(
            'canvas.toBlob',
            $src,
            'OFFLINE-PHOTOS-1: TicketPhotoAnnotator must call canvas.toBlob so the blob is what lands in IDB (not the dataURL string).',
        );
        $this->assertStringContainsString(
            'discardPhoto',
            $src,
            'OFFLINE-PHOTOS-3: TicketPhotoAnnotator must discardPhoto on successful upload to clean up the IDB entry.',
        );
        $this->assertStringContainsString(
            'markFailed',
            $src,
            'OFFLINE-PHOTOS-2: TicketPhotoAnnotator must mark the entry failed on Inertia onError so the retry UI can surface it.',
        );
    }

    public function test_photo_upload_status_list_component_exists(): void
    {
        $path = resource_path('js/Components/Offline/PhotoUploadStatusList.vue');
        $this->assertFileExists($path, 'OFFLINE-PHOTOS-2: PhotoUploadStatusList.vue must exist.');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString(
            'listPendingForTicket',
            $src,
            'OFFLINE-PHOTOS-2: PhotoUploadStatusList must filter the offline photo store by the current ticket.',
        );
        $this->assertStringContainsString(
            'discardPhoto',
            $src,
            'OFFLINE-PHOTOS-2: PhotoUploadStatusList must allow per-row cancel via discardPhoto.',
        );
        foreach (['pending', 'uploading', 'failed'] as $status) {
            $this->assertStringContainsString(
                "'{$status}'",
                $src,
                "OFFLINE-PHOTOS-2: PhotoUploadStatusList must render status '{$status}'.",
            );
        }
        $this->assertStringContainsString(
            'data-testid',
            $src,
            'OFFLINE-PHOTOS-2: PhotoUploadStatusList must expose data-testid on entries so Playwright/Vitest can target them.',
        );
    }

    public function test_offline_photo_store_enforces_budget_via_oldest_first_eviction(): void
    {
        $src = (string) file_get_contents(resource_path('js/lib/offlinePhotoStore.ts'));

        $this->assertStringContainsString(
            'items.sort((a, b) => a.createdAt - b.createdAt)',
            $src,
            'OFFLINE-PHOTOS-3: enforceBudget must sort by createdAt ascending so the oldest entry is evicted first.',
        );
        $this->assertStringContainsString(
            'PhotoQuotaExceededError',
            $src,
            'OFFLINE-PHOTOS-3: enforceBudget must throw PhotoQuotaExceededError when even after eviction the incoming blob would not fit.',
        );
    }
}
