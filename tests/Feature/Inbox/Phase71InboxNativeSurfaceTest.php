<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use Tests\TestCase;

/**
 * Phase-71 INBOX-NATIVE-UX surface map. Guards the native-messaging UI
 * surface against drift as each sub-phase lands (BUBBLES, COMPOSER,
 * LIVE-DELIVERY, REPLY-QUOTE, REACTIONS, RICH-MEDIA). Asserts the shared
 * presentational components exist and that both landlord + tenant Show
 * pages route their message rendering through them — no per-page bubble
 * markup duplication.
 */
class Phase71InboxNativeSurfaceTest extends TestCase
{
    private function js(string $relative): string
    {
        $path = base_path('resources/js/'.$relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_bubbles_shared_components_exist(): void
    {
        $bubble = $this->js('Components/Inbox/MessageBubble.vue');
        $thread = $this->js('Components/Inbox/ChatThread.vue');

        // The bubble owns the aligned-bubble, grouped-avatar and seen-tick UI.
        $this->assertStringContainsString('data-testid="chat-bubble"', $bubble);
        $this->assertStringContainsString('data-testid="chat-system"', $bubble);
        $this->assertStringContainsString('data-testid="message-seen"', $bubble);
        $this->assertStringContainsString('export interface BubbleMessage', $bubble);

        // The thread region owns day separators, the unread divider and grouping.
        $this->assertStringContainsString('data-testid="chat-day-separator"', $thread);
        $this->assertStringContainsString('data-testid="chat-unread-divider"', $thread);
        $this->assertStringContainsString('<MessageBubble', $thread);
    }

    public function test_both_show_pages_render_chat_thread(): void
    {
        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $src = $this->js($page);
            $this->assertStringContainsString(
                "import ChatThread from '@/Components/Inbox/ChatThread.vue'",
                $src,
            );
            $this->assertStringContainsString('<ChatThread', $src);
            $this->assertStringContainsString(':others-read-at="othersReadAt"', $src);
        }
    }

    public function test_chat_lang_keys_exist_across_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $chat = __('inbox.chat', [], $locale);
            $this->assertIsArray($chat);
            $this->assertArrayHasKey('today', $chat);
            $this->assertArrayHasKey('yesterday', $chat);
            $this->assertArrayHasKey('unread', $chat);
            $this->assertArrayHasKey('sent', $chat);
        }
    }
}
