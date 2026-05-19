<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use Tests\TestCase;

/**
 * Phase-64 INBOX-MOUNT-1/2/3 watchdog: PHPUnit cannot compile Vue,
 * so we assert mount-token presence in the file contents (per Phase
 * 51 VUE-TAIL-1 contract). Behavioural verification happens via the
 * Inertia integration tests in Phase63 *Test files.
 */
class Phase64InboxMountTest extends TestCase
{
    public function test_inbox_bell_component_present_with_expected_tokens(): void
    {
        $path = base_path('resources/js/Components/InboxBell.vue');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['inbox_unread_total', 'EnvelopeIcon', 'message-threads.index', 'tenant.inbox.index', 'inbox-bell'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "InboxBell.vue missing expected token '{$token}'",
            );
        }
    }

    public function test_initiate_thread_dialog_present_with_expected_tokens(): void
    {
        $path = base_path('resources/js/Components/Inbox/InitiateThreadDialog.vue');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['message-threads.store', 'participants', 'textarea', 'initiate-thread-dialog'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "InitiateThreadDialog.vue missing expected token '{$token}'",
            );
        }
    }

    public function test_authenticated_layout_imports_and_mounts_inbox_bell(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            "import InboxBell from '@/Components/InboxBell.vue'",
            $contents,
        );
        $this->assertStringContainsString('<InboxBell />', $contents);
    }

    public function test_authenticated_layout_landlord_nav_contains_messages_entry(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString("route('message-threads.index')", $contents);
        $this->assertStringContainsString("'nav.messages'", $contents);
    }

    public function test_authenticated_layout_tenant_nav_contains_inbox_entry(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString("route('tenant.inbox.index')", $contents);
        $this->assertStringContainsString("'nav.inbox'", $contents);
    }

    public function test_tenants_show_page_mounts_message_cta(): void
    {
        $contents = file_get_contents(base_path('resources/js/Pages/Tenants/Show.vue'));

        $this->assertStringContainsString('InitiateThreadDialog', $contents);
        $this->assertStringContainsString('tenant-message-cta', $contents);
        $this->assertStringContainsString('messageDialog?.open()', $contents);
    }

    public function test_nav_messages_and_inbox_i18n_keys_present_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $bundle = json_decode(
                file_get_contents(base_path("lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $this->assertArrayHasKey(
                'messages',
                $bundle['nav'] ?? [],
                "lang/{$locale}.json is missing nav.messages",
            );
            $this->assertArrayHasKey(
                'inbox',
                $bundle['nav'] ?? [],
                "lang/{$locale}.json is missing nav.inbox",
            );
        }
    }
}
