<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-71 REACTIONS (RX-1/RX-2): the toggle endpoint is participant-gated and
 * allow-list-constrained, cross-thread message ids are rejected, and the show
 * payload hydrates each message's grouped reaction summary with a viewer-aware
 * `reacted` flag.
 */
class Phase71ReactionsTest extends TestCase
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
        foreach ($participants as $p) {
            $thread->participants()->attach($p->id, [
                'role' => $p->is($this->landlord) ? MessageThread::ROLE_LANDLORD : MessageThread::ROLE_TENANT,
            ]);
        }

        return $thread;
    }

    private function messageIn(MessageThread $thread, User $sender): Message
    {
        return $thread->messages()->create(['sender_id' => $sender->id, 'body' => 'A message.']);
    }

    public function test_toggle_adds_then_removes_a_reaction(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $this->messageIn($thread, $this->tenantA);
        $route = route('message-threads.messages.react', [$thread->id, $message->id]);

        $this->actingAs($this->landlord)->post($route, ['emoji' => '👍'])->assertRedirect();
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->landlord->id,
            'emoji' => '👍',
        ]);

        $this->actingAs($this->landlord)->post($route, ['emoji' => '👍'])->assertRedirect();
        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->landlord->id,
            'emoji' => '👍',
        ]);
    }

    public function test_non_participant_cannot_react(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $this->messageIn($thread, $this->tenantA);

        $this->actingAs($this->tenantB)
            ->post(route('message-threads.messages.react', [$thread->id, $message->id]), ['emoji' => '👍'])
            ->assertForbidden();

        $this->assertSame(0, MessageReaction::query()->count());
    }

    public function test_cross_thread_message_is_rejected(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $otherThread = $this->threadWith($this->landlord, $this->tenantA);
        $foreignMessage = $this->messageIn($otherThread, $this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('message-threads.messages.react', [$thread->id, $foreignMessage->id]), ['emoji' => '👍'])
            ->assertNotFound();

        $this->assertSame(0, MessageReaction::query()->count());
    }

    public function test_emoji_must_be_in_the_allow_list(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $this->messageIn($thread, $this->tenantA);

        $this->actingAs($this->landlord)
            ->post(route('message-threads.messages.react', [$thread->id, $message->id]), ['emoji' => '💣'])
            ->assertSessionHasErrors(['emoji']);

        $this->assertSame(0, MessageReaction::query()->count());
    }

    public function test_show_payload_hydrates_grouped_reaction_summary(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $this->messageIn($thread, $this->tenantA);

        MessageReaction::create(['message_id' => $message->id, 'user_id' => $this->landlord->id, 'emoji' => '👍']);
        MessageReaction::create(['message_id' => $message->id, 'user_id' => $this->tenantA->id, 'emoji' => '👍']);

        $this->actingAs($this->landlord)
            ->get(route('message-threads.show', $thread))
            ->assertInertia(fn ($page) => $page
                ->component('MessageThreads/Show')
                ->where('thread.messages.0.reactions.0.emoji', '👍')
                ->where('thread.messages.0.reactions.0.count', 2)
                ->where('thread.messages.0.reactions.0.reacted', true)
                ->where('reactionEmojis', config('inbox.reactions')),
            );
    }

    public function test_tenant_can_react_on_their_own_thread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $this->messageIn($thread, $this->landlord);

        $this->actingAs($this->tenantA)
            ->post(route('tenant.inbox.messages.react', [$thread->id, $message->id]), ['emoji' => '❤️'])
            ->assertRedirect();

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->tenantA->id,
            'emoji' => '❤️',
        ]);
    }
}
