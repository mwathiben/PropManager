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

    public function test_composer_shared_component(): void
    {
        $composer = $this->js('Components/Inbox/ChatComposer.vue');

        // Enter-to-send, Shift+Enter newline, IME-composition aware.
        $this->assertStringContainsString('isComposing', $composer);
        $this->assertStringContainsString("event.key === 'Enter'", $composer);
        $this->assertStringContainsString('shiftKey', $composer);

        // Auto-grow textarea + attachment tray + Phase-67 scan error preserved.
        $this->assertStringContainsString('scrollHeight', $composer);
        $this->assertStringContainsString('AttachmentPreviewList', $composer);
        $this->assertStringContainsString('data-testid="attachment-blocked"', $composer);

        // Locked state + char counter (COMPOSER-3).
        $this->assertStringContainsString('data-testid="composer-locked"', $composer);
        $this->assertStringContainsString('data-testid="composer-counter"', $composer);

        // Both pages mount the shared composer, preserving their compose hooks.
        $land = $this->js('Pages/MessageThreads/Show.vue');
        $this->assertStringContainsString('<ChatComposer', $land);
        $this->assertStringContainsString('testid="message-compose"', $land);

        $tenant = $this->js('Pages/Tenant/Inbox/Show.vue');
        $this->assertStringContainsString('<ChatComposer', $tenant);
        $this->assertStringContainsString('testid="tenant-message-compose"', $tenant);
    }

    public function test_typing_bubble_in_chat_region(): void
    {
        $thread = $this->js('Components/Inbox/ChatThread.vue');
        $this->assertStringContainsString('data-testid="chat-typing-bubble"', $thread);
        // prefers-reduced-motion: the bounce only animates under motion-safe.
        $this->assertStringContainsString('motion-safe:animate-bounce', $thread);

        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $this->assertStringContainsString(':typing-names="typingNames"', $this->js($page));
        }
    }

    public function test_live_delivery_stream(): void
    {
        // Pure state machine: ingest + the optimistic lifecycle (the Echo channel
        // is owned by the page, so there is one subscriber per channel).
        $stream = $this->js('composables/useThreadStream.ts');
        $this->assertStringContainsString('function ingest', $stream);
        $this->assertStringContainsString('addOptimistic', $stream);
        $this->assertStringContainsString('resolveOptimistic', $stream);
        $this->assertStringContainsString('failOptimistic', $stream);

        // Per-bubble sending / failed states (seen tick already shipped in BUBBLES).
        $bubble = $this->js('Components/Inbox/MessageBubble.vue');
        $this->assertStringContainsString('data-testid="message-sending"', $bubble);
        $this->assertStringContainsString('data-testid="message-failed"', $bubble);

        // Jump-to-latest pill + scroll management live in the chat region.
        $thread = $this->js('Components/Inbox/ChatThread.vue');
        $this->assertStringContainsString('data-testid="chat-jump-latest"', $thread);

        // Both pages subscribe .message.posted on their own channel, feed ingest,
        // and preserve state across sends so the live list survives the reload.
        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $src = $this->js($page);
            $this->assertStringContainsString('useThreadStream', $src);
            $this->assertStringContainsString('.message.posted', $src);
            $this->assertStringContainsString('ingest(', $src);
            $this->assertStringContainsString('preserveState: true', $src);
            $this->assertStringContainsString('@retry="onRetry"', $src);
        }
    }

    public function test_chat_lang_keys_exist_across_locales(): void
    {
        $required = [
            'today', 'yesterday', 'unread', 'sent', 'placeholder', 'send', 'attach',
            'body_label', 'locked', 'chars_remaining', 'jump_latest', 'sending', 'retry',
        ];

        foreach (['en', 'sw', 'ar'] as $locale) {
            $chat = __('inbox.chat', [], $locale);
            $this->assertIsArray($chat);
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $chat, "inbox.chat.{$key} missing for {$locale}");
            }
        }
    }
}
