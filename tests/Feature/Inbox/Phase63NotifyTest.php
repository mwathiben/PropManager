<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Events\MessagePosted;
use App\Listeners\SendUnreadMessageFallback;
use App\Models\Lease;
use App\Models\MessageThread;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-63 INBOX-NOTIFY-1/2/3 watchdog: TYPE_NEW_MESSAGE registration,
 * preference column, listener gating, digest cron, last_active_at
 * presence cursor + Inertia debounce.
 */
class Phase63NotifyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    private function threadWith(User $landlord, User $tenant): MessageThread
    {
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);
        $thread->participants()->attach($landlord->id, ['role' => MessageThread::ROLE_LANDLORD]);
        $thread->participants()->attach($tenant->id, ['role' => MessageThread::ROLE_TENANT]);

        return $thread;
    }

    public function test_notification_type_new_message_registered(): void
    {
        $this->assertSame('new_message', Notification::TYPE_NEW_MESSAGE);
        $this->assertArrayHasKey(
            Notification::TYPE_NEW_MESSAGE,
            Notification::TYPE_URGENCY_MAP,
        );
        $this->assertSame(
            Notification::URGENCY_IMPORTANT,
            Notification::TYPE_URGENCY_MAP[Notification::TYPE_NEW_MESSAGE],
        );
    }

    public function test_notification_preference_new_message_enabled_column_present(): void
    {
        $this->assertTrue(Schema::hasColumn('notification_preferences', 'new_message_enabled'));

        $pref = NotificationPreference::getOrCreate($this->tenant->id, $this->landlord->id)->refresh();

        $this->assertTrue(
            (bool) $pref->new_message_enabled,
            'new_message_enabled defaults to TRUE so opt-out is explicit.',
        );
    }

    public function test_users_last_active_at_column_present(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'last_active_at'));
    }

    public function test_listener_implements_should_queue_with_phase16_backoff(): void
    {
        $listener = new SendUnreadMessageFallback(app(NotificationService::class));

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame(4, $listener->tries);
        $this->assertSame([30, 60, 300, 1800], $listener->backoff);
    }

    public function test_listener_fires_notification_when_recipient_offline(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Hello tenant — please respond when you have time.',
        ]);

        $this->tenant->last_active_at = null;
        $this->tenant->save();

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (
                int $recipientId,
                string $type,
                string $subject,
                string $messageBody,
                ?array $data,
                ?int $landlordId,
            ) use ($thread, $message) {
                return $recipientId === $this->tenant->id
                    && $type === Notification::TYPE_NEW_MESSAGE
                    && $data !== null
                    && ($data['thread_id'] ?? null) === $thread->id
                    && ($data['message_id'] ?? null) === $message->id
                    && $landlordId === $thread->landlord_id;
            });
        $this->app->instance(NotificationService::class, $mock);

        $listener = new SendUnreadMessageFallback($mock);
        $listener->handle(new MessagePosted($message));
    }

    public function test_listener_skips_when_recipient_recently_active(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Hello',
        ]);

        $this->tenant->last_active_at = now()->subMinutes(2);
        $this->tenant->save();

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldNotReceive('send');

        $listener = new SendUnreadMessageFallback($mock);
        $listener->handle(new MessagePosted($message));

        $this->addToAssertionCount(1);
    }

    public function test_listener_skips_system_messages(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->recordSystemEvent('Thread opened');

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldNotReceive('send');

        $listener = new SendUnreadMessageFallback($mock);
        $listener->handle(new MessagePosted($message));

        $this->addToAssertionCount(1);
    }

    public function test_listener_idempotent_per_message_user(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Just one fallback please',
        ]);

        $this->tenant->last_active_at = null;
        $this->tenant->save();

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldReceive('send')->once();

        $listener = new SendUnreadMessageFallback($mock);
        $listener->handle(new MessagePosted($message));
        $listener->handle(new MessagePosted($message));
    }

    public function test_digest_command_dispatches_for_stale_unread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Are you still around?',
        ]);
        // Force the historical timestamps AFTER create so the
        // Message::booted last_message_at bump doesn't clobber them.
        $message->forceFill(['created_at' => now()->subMinutes(30)])->save();
        $thread->forceFill(['last_message_at' => now()->subMinutes(30)])->save();

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldReceive('send')->once();
        $this->app->instance(NotificationService::class, $mock);

        $this->artisan('messages:notify-unread-fallback')
            ->assertSuccessful();
    }

    public function test_digest_command_skips_when_thread_too_fresh(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'just-sent',
        ]);
        $message->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $thread->forceFill(['last_message_at' => now()->subMinutes(5)])->save();

        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldNotReceive('send');
        $this->app->instance(NotificationService::class, $mock);

        $this->artisan('messages:notify-unread-fallback')->assertSuccessful();
    }

    public function test_messages_notify_unread_fallback_scheduled_every_15_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(
            fn ($e) => str_contains($e->command ?? '', 'messages:notify-unread-fallback'),
        );

        $this->assertCount(1, $events, 'messages:notify-unread-fallback must be scheduled exactly once.');
        $event = $events->first();
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertSame(
            'Africa/Nairobi',
            $event->timezone instanceof \DateTimeZone
                ? $event->timezone->getName()
                : (string) $event->timezone,
        );
    }

    public function test_handle_inertia_requests_touches_last_active_at_after_60s(): void
    {
        $this->tenant->last_active_at = now()->subMinutes(3);
        $this->tenant->save();

        $beforeTimestamp = $this->tenant->fresh()->last_active_at->timestamp;

        $this->actingAs($this->tenant)->get('/tenant/inbox')->assertOk();

        $afterTimestamp = $this->tenant->fresh()->last_active_at->timestamp;

        $this->assertGreaterThan($beforeTimestamp, $afterTimestamp);
    }

    public function test_handle_inertia_requests_debounces_within_60s(): void
    {
        $this->tenant->last_active_at = now()->subSeconds(30);
        $this->tenant->save();

        $beforeTimestamp = $this->tenant->fresh()->last_active_at->timestamp;

        $this->actingAs($this->tenant)->get('/tenant/inbox')->assertOk();

        $afterTimestamp = $this->tenant->fresh()->last_active_at->timestamp;

        $this->assertSame($beforeTimestamp, $afterTimestamp);
    }

    public function test_inbox_notification_i18n_keys_parity(): void
    {
        $en = require base_path('lang/en/inbox.php');
        $sw = require base_path('lang/sw/inbox.php');
        $ar = require base_path('lang/ar/inbox.php');

        foreach (['en' => $en, 'sw' => $sw, 'ar' => $ar] as $locale => $bundle) {
            $this->assertArrayHasKey(
                'notification',
                $bundle,
                "lang/{$locale}/inbox.php is missing the notification namespace.",
            );
            $this->assertArrayHasKey('subject', $bundle['notification']);
            $this->assertArrayHasKey('sender_unknown', $bundle['notification']);
        }
    }
}
