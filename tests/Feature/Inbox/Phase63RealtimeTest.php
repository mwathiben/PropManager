<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Events\MessagePosted;
use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-63 INBOX-REALTIME-1/2/3 watchdog: broadcast event +
 * participant-aware channel auth + read receipts + typing composable.
 */
class Phase63RealtimeTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenantA;

    private User $tenantB;

    private Lease $leaseA;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];

        ['tenant' => $this->tenantA, 'lease' => $this->leaseA] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        ['tenant' => $this->tenantB] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->get(1),
        );
    }

    private function threadWith(User ...$participants): MessageThread
    {
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);

        foreach ($participants as $user) {
            $thread->participants()->attach($user->id, [
                'role' => match (true) {
                    $user->isLandlord() => MessageThread::ROLE_LANDLORD,
                    $user->isCaretaker() => MessageThread::ROLE_CARETAKER,
                    default => MessageThread::ROLE_TENANT,
                },
            ]);
        }

        return $thread;
    }

    public function test_message_posted_event_implements_should_broadcast(): void
    {
        $this->assertTrue(is_subclass_of(MessagePosted::class, ShouldBroadcast::class));

        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'hi',
        ]);

        $event = new MessagePosted($message);
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-inbox.thread.'.$thread->id, $channels[0]->name);

        $this->assertSame('message.posted', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($message->id, $payload['message_id']);
        $this->assertSame($thread->id, $payload['thread_id']);
        $this->assertSame($this->landlord->id, $payload['sender']['id']);
        $this->assertSame('hi', $payload['body']);
    }

    public function test_message_posted_dispatched_when_landlord_posts(): void
    {
        Event::fake([MessagePosted::class]);

        $this->actingAs($this->landlord)->post('/message-threads', [
            'participants' => [$this->tenantA->id],
            'body' => 'Hello',
        ]);

        Event::assertDispatched(MessagePosted::class, function (MessagePosted $event) {
            return $event->message->body === 'Hello'
                && $event->message->sender_id === $this->landlord->id;
        });
    }

    public function test_message_posted_dispatched_on_reply(): void
    {
        Event::fake([MessagePosted::class]);

        $thread = $this->threadWith($this->landlord, $this->tenantA);

        $this->actingAs($this->tenantA)->post(
            route('tenant.inbox.messages.store', $thread),
            ['body' => 'tenant reply'],
        );

        Event::assertDispatched(MessagePosted::class, function (MessagePosted $event) {
            return $event->message->body === 'tenant reply'
                && $event->message->sender_id === $this->tenantA->id;
        });
    }

    public function test_channel_auth_closure_grants_participant_and_denies_non_participant(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);

        // Invoke the registered channel-auth closure directly. The
        // /broadcasting/auth HTTP path is exercised in integration but
        // returns 200 unconditionally under BROADCAST_CONNECTION=null
        // (NullBroadcaster::auth is a no-op) so it cannot prove the
        // security property here. The closure on the Broadcaster
        // instance is the actual gate.
        $broadcaster = \Illuminate\Support\Facades\Broadcast::driver();
        $closures = $this->extractChannelClosures($broadcaster);

        $this->assertArrayHasKey('inbox.thread.{threadId}', $closures);
        $closure = $closures['inbox.thread.{threadId}'];

        $this->assertTrue(
            (bool) $closure($this->tenantA, $thread->id),
            'Participant tenantA must be granted access to inbox.thread.{id} channel.',
        );

        $this->assertFalse(
            (bool) $closure($this->tenantB, $thread->id),
            'Non-participant tenantB (same landlord) must be denied — pivot is the gate, not landlord_id.',
        );

        $this->assertTrue(
            (bool) $closure($this->landlord, $thread->id),
            'Landlord (participant) must be granted access.',
        );
    }

    /**
     * @return array<string, \Closure>
     */
    private function extractChannelClosures(object $manager): array
    {
        // Walk up the inheritance / decorator chain to find the
        // `channels` array. Laravel keeps it on BroadcastManager
        // as a protected property.
        $ref = new \ReflectionObject($manager);
        while ($ref !== false && ! $ref->hasProperty('channels')) {
            $ref = $ref->getParentClass();
        }

        if ($ref === false) {
            $this->fail('Could not locate channels registry on BroadcastManager.');
        }

        $property = $ref->getProperty('channels');
        $property->setAccessible(true);

        return $property->getValue($manager);
    }

    public function test_read_receipt_updates_last_read_at_for_caller_only(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'check this',
        ]);

        $this->assertNull(
            MessageThreadParticipant::where('thread_id', $thread->id)
                ->where('user_id', $this->tenantA->id)
                ->value('last_read_at'),
        );

        $response = $this->actingAs($this->tenantA)
            ->patch(route('messages.read', $message));

        $response->assertRedirect();

        $tenantRead = MessageThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $this->tenantA->id)
            ->value('last_read_at');
        $landlordRead = MessageThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $this->landlord->id)
            ->value('last_read_at');

        $this->assertNotNull($tenantRead);
        $this->assertNull($landlordRead, 'Read receipt must only affect the caller, not other participants.');
    }

    public function test_read_receipt_denies_non_participant(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'private',
        ]);

        $response = $this->actingAs($this->tenantB)
            ->patch(route('messages.read', $message));

        $response->assertForbidden();
    }

    public function test_read_receipt_is_idempotent(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'a',
        ]);

        // First read marks last_read_at = message.created_at.
        $this->actingAs($this->tenantA)->patch(route('messages.read', $message));
        $first = MessageThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $this->tenantA->id)
            ->value('last_read_at');

        // Advance system clock + re-mark; last_read_at must NOT regress nor
        // jump to "now" if the stored value is already >= message.created_at.
        Carbon::setTestNow(Carbon::now()->addMinutes(10));
        $this->actingAs($this->tenantA)->patch(route('messages.read', $message));
        $second = MessageThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $this->tenantA->id)
            ->value('last_read_at');

        $this->assertEquals($first->timestamp, $second->timestamp);

        Carbon::setTestNow();
    }

    public function test_handle_inertia_requests_shares_inbox_unread_total(): void
    {
        Cache::flush();

        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'first',
        ]);
        $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'second',
        ]);

        $response = $this->actingAs($this->tenantA)->get('/tenant/inbox');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.inbox_unread_total', 2)
        );
    }

    public function test_use_typing_indicator_composable_exists_with_expected_tokens(): void
    {
        $path = base_path('resources/js/composables/useTypingIndicator.ts');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['useTypingIndicator', 'whisper', 'listenForWhisper', 'inbox.thread.', 'typingUser'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "useTypingIndicator.ts is missing expected token '{$token}'",
            );
        }
    }

    public function test_inbox_channel_registered_in_channels_file(): void
    {
        $contents = file_get_contents(base_path('routes/channels.php'));
        $this->assertStringContainsString('inbox.thread.{threadId}', $contents);
        $this->assertStringContainsString('message_thread_participants', $contents);
    }

    public function test_messages_read_route_registered_with_auth_middleware(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'messages.read');

        $this->assertNotNull($route);
        $this->assertContains('PATCH', $route->methods());
        $this->assertContains('auth', $route->middleware());
    }
}
