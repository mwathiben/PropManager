<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Events\MessagePosted;
use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-71 REPLY-QUOTE (R-1/R-3): a reply may quote a message in the SAME
 * thread, cross-thread quotes are rejected at validation, the show payload
 * hydrates each message's compact reply preview, and the MessagePosted
 * broadcast carries the same preview shape.
 */
class Phase71ReplyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenantA;

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

    public function test_same_thread_reply_persists_reply_to_id(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $original = $thread->messages()->create([
            'sender_id' => $this->tenantA->id,
            'body' => 'When is rent due?',
        ]);

        $response = $this->actingAs($this->landlord)->post(
            route('message-threads.messages.store', $thread),
            ['body' => 'The 5th of each month.', 'reply_to_id' => $original->id],
        );

        $response->assertRedirect();
        $reply = $thread->messages()->latest('id')->first();
        $this->assertSame($original->id, $reply->reply_to_id);
        $this->assertSame($original->id, $reply->replyTo->id);
    }

    public function test_cross_thread_reply_to_id_is_rejected(): void
    {
        $threadA = $this->threadWith($this->landlord, $this->tenantA);
        $otherThread = $this->threadWith($this->landlord, $this->tenantA);
        $foreignMessage = $otherThread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Message in another thread.',
        ]);

        $response = $this->actingAs($this->landlord)->post(
            route('message-threads.messages.store', $threadA),
            ['body' => 'Trying to quote a foreign message.', 'reply_to_id' => $foreignMessage->id],
        );

        $response->assertSessionHasErrors(['reply_to_id']);
        $this->assertSame(0, $threadA->messages()->count());
    }

    public function test_show_payload_includes_reply_preview(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $original = $thread->messages()->create([
            'sender_id' => $this->tenantA->id,
            'body' => 'Original question body.',
        ]);
        $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'reply_to_id' => $original->id,
            'body' => 'The answer.',
        ]);

        $this->actingAs($this->landlord)
            ->get(route('message-threads.show', $thread))
            ->assertInertia(fn ($page) => $page
                ->component('MessageThreads/Show')
                ->where('thread.messages.1.reply_to.id', $original->id)
                ->where('thread.messages.1.reply_to.sender_name', $this->tenantA->name)
                ->where('thread.messages.1.reply_to.body', 'Original question body.'),
            );
    }

    public function test_broadcast_payload_carries_reply_preview(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $original = $thread->messages()->create([
            'sender_id' => $this->tenantA->id,
            'body' => 'Quote me.',
        ]);
        $reply = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'reply_to_id' => $original->id,
            'body' => 'Quoted you.',
        ]);

        $payload = (new MessagePosted($reply))->broadcastWith();

        $this->assertSame($original->id, $payload['reply_to']['id']);
        $this->assertSame($this->tenantA->name, $payload['reply_to']['sender_name']);
        $this->assertSame('Quote me.', $payload['reply_to']['body']);
    }

    public function test_reply_preview_is_null_when_the_quoted_original_is_soft_deleted(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $original = $thread->messages()->create([
            'sender_id' => $this->tenantA->id,
            'body' => 'This will be retracted.',
        ]);
        $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'reply_to_id' => $original->id,
            'body' => 'A reply that quotes it.',
        ]);

        $original->delete();

        $this->actingAs($this->landlord)
            ->get(route('message-threads.show', $thread))
            ->assertInertia(fn ($page) => $page
                ->component('MessageThreads/Show')
                // The original is excluded from the thread, leaving the reply at
                // index 0 with its quote degraded to null.
                ->where('thread.messages.0.body', 'A reply that quotes it.')
                ->where('thread.messages.0.reply_to', null),
            );
    }

    public function test_tenant_cannot_quote_another_tenant_thread_message(): void
    {
        $landlordThread = $this->threadWith($this->landlord, $this->tenantA);
        $tenantThread = $this->threadWith($this->landlord, $this->tenantA);
        $landlordMessage = $landlordThread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'Landlord-thread message.',
        ]);

        $response = $this->actingAs($this->tenantA)->post(
            route('tenant.inbox.messages.store', $tenantThread),
            ['body' => 'Cross-thread quote attempt.', 'reply_to_id' => $landlordMessage->id],
        );

        $response->assertSessionHasErrors(['reply_to_id']);
    }
}
