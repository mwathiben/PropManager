<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use Tests\TestCase;

/**
 * Phase-64 INBOX-POLISH-1/2/3 watchdog: virtualization, thumbnail
 * preview, drag-and-drop zone.
 */
class Phase64InboxPolishTest extends TestCase
{
    public function test_virtual_message_list_component_with_windowing_tokens(): void
    {
        $path = base_path('resources/js/Components/Inbox/VirtualMessageList.vue');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['IntersectionObserver', 'VIRTUALIZE_THRESHOLD', 'visibleStart', 'visibleEnd', 'shouldVirtualize'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "VirtualMessageList.vue missing expected token '{$token}'",
            );
        }
    }

    public function test_attachment_preview_list_component_with_object_url_tokens(): void
    {
        $path = base_path('resources/js/Components/Inbox/AttachmentPreviewList.vue');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['URL.createObjectURL', 'URL.revokeObjectURL', 'IMAGE_MIME_PREFIX', 'attachment-preview-list'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "AttachmentPreviewList.vue missing expected token '{$token}'",
            );
        }
    }

    public function test_use_file_drop_zone_composable_with_drag_handlers(): void
    {
        $path = base_path('resources/js/composables/useFileDropZone.ts');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['handleDragEnter', 'handleDragOver', 'handleDragLeave', 'handleDrop', 'isDragging', 'DataTransfer'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "useFileDropZone.ts missing expected token '{$token}'",
            );
        }
    }
}
