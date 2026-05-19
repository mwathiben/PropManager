<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use Tests\TestCase;

/**
 * Phase-63 INBOX-CI-1: cross-category surface map. Asserts every
 * primitive that the inbox feature depends on actually exists in the
 * repo — catches accidental deletion / refactor drift in a single
 * watchdog.
 */
class Phase63InboxSurfaceTest extends TestCase
{
    public function test_models_present_with_expected_traits(): void
    {
        $this->assertTrue(class_exists(\App\Models\MessageThread::class));
        $this->assertTrue(class_exists(\App\Models\Message::class));
        $this->assertTrue(class_exists(\App\Models\MessageThreadParticipant::class));

        $threadUses = class_uses(\App\Models\MessageThread::class);
        $this->assertContains(\App\Traits\Auditable::class, $threadUses);
        $this->assertContains(\App\Traits\TenantScope::class, $threadUses);
        $this->assertContains(\Illuminate\Database\Eloquent\SoftDeletes::class, $threadUses);

        $messageUses = class_uses(\App\Models\Message::class);
        $this->assertContains(\App\Traits\Auditable::class, $messageUses);
        $this->assertContains(\Illuminate\Database\Eloquent\SoftDeletes::class, $messageUses);
        $this->assertNotContains(\App\Traits\TenantScope::class, $messageUses);
    }

    public function test_policies_registered(): void
    {
        $provider = new \App\Providers\AuthServiceProvider(app());
        $ref = new \ReflectionClass($provider);
        $prop = $ref->getProperty('policies');
        $prop->setAccessible(true);
        $policies = $prop->getValue($provider);

        $this->assertArrayHasKey(\App\Models\MessageThread::class, $policies);
        $this->assertArrayHasKey(\App\Models\Message::class, $policies);
    }

    public function test_event_class_implements_should_broadcast(): void
    {
        $this->assertTrue(class_exists(\App\Events\MessagePosted::class));
        $this->assertTrue(is_subclass_of(
            \App\Events\MessagePosted::class,
            \Illuminate\Contracts\Broadcasting\ShouldBroadcast::class,
        ));
    }

    public function test_listener_class_implements_should_queue(): void
    {
        $this->assertTrue(class_exists(\App\Listeners\SendUnreadMessageFallback::class));
        $this->assertTrue(is_subclass_of(
            \App\Listeners\SendUnreadMessageFallback::class,
            \Illuminate\Contracts\Queue\ShouldQueue::class,
        ));
    }

    public function test_console_commands_registered(): void
    {
        $artisan = app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $all = $artisan->all();

        $this->assertArrayHasKey('messages:notify-unread-fallback', $all);
        $this->assertArrayHasKey('messages:enforce-retention', $all);
    }

    public function test_route_table_complete(): void
    {
        $expected = [
            'message-threads.index',
            'message-threads.show',
            'message-threads.store',
            'message-threads.messages.store',
            'message-threads.archive',
            'message-threads.lock',
            'message-threads.unlock',
            'tenant.inbox.index',
            'tenant.inbox.show',
            'tenant.inbox.store',
            'tenant.inbox.messages.store',
            'messages.read',
            'messages.destroy',
        ];

        $names = collect(\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values()
            ->all();

        foreach ($expected as $name) {
            $this->assertContains($name, $names, "Route name '{$name}' is not registered.");
        }
    }

    public function test_pm_offline_messages_queue_wired_into_sw_and_composable(): void
    {
        $sw = file_get_contents(base_path('resources/js/sw.ts'));
        $this->assertStringContainsString('pm-offline-messages', $sw);

        $composable = file_get_contents(base_path('resources/js/composables/useBackgroundSync.ts'));
        $this->assertStringContainsString('pm-offline-messages', $composable);
        $this->assertStringContainsString("'messages'", $composable);
    }

    public function test_inbox_runbook_present_with_load_bearing_sections(): void
    {
        $path = base_path('docs/runbooks/inbox.md');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach ([
            'Schema',
            'Thread lifecycle',
            'Participant roles',
            'Real-time',
            'Notification fallback',
            'Retention',
            'Rate limiting',
            'Offline-write queue',
        ] as $section) {
            $this->assertStringContainsString(
                $section,
                $contents,
                "inbox.md missing section: {$section}",
            );
        }
    }

    public function test_alert_thresholds_extended_with_inbox_rows(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('inbox_unread_fallback_count', $contents);
        $this->assertStringContainsString('inbox_rate_limit_hits_count', $contents);
    }

    public function test_inbox_i18n_parity_across_locales(): void
    {
        $en = require base_path('lang/en/inbox.php');
        $sw = require base_path('lang/sw/inbox.php');
        $ar = require base_path('lang/ar/inbox.php');

        $flatten = static function (array $arr, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($arr as $k => $v) {
                $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
                if (is_array($v)) {
                    $out = array_merge($out, $flatten($v, $key));
                } else {
                    $out[] = $key;
                }
            }
            sort($out);

            return $out;
        };

        $this->assertSame($flatten($en), $flatten($sw));
        $this->assertSame($flatten($en), $flatten($ar));
    }

    public function test_rate_limiter_for_messages_registered(): void
    {
        $resolver = \Illuminate\Support\Facades\RateLimiter::limiter('messages');
        $this->assertNotNull($resolver);
    }

    public function test_inbox_channel_registered_with_participants_pivot_check(): void
    {
        $channels = file_get_contents(base_path('routes/channels.php'));
        $this->assertStringContainsString('inbox.thread.{threadId}', $channels);
        $this->assertStringContainsString('message_thread_participants', $channels);
    }
}
