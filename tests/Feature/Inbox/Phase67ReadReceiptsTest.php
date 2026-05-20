<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Events\MessageRead;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-67 READ-RECEIPTS CI: cursor advance broadcasts MessageRead (only
 * on a real advance), mark-all-read, read-by computation, and the
 * non-participant block.
 */
class Phase67ReadReceiptsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:User,2:MessageThread,3:Message} */
    private function scenario(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $thread = MessageThread::create(['landlord_id' => $landlord->id, 'title' => 'Re: rent']);
        $thread->participants()->attach($landlord->id, ['role' => 'landlord']);
        $thread->participants()->attach($tenant->id, ['role' => 'tenant']);

        // A message FROM the tenant that the landlord will read.
        $message = $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'Hello landlord']);

        return [$landlord, $tenant, $thread, $message];
    }

    private function pivot(MessageThread $thread, User $user): MessageThreadParticipant
    {
        return MessageThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function test_reading_advances_cursor_and_broadcasts(): void
    {
        Event::fake([MessageRead::class]);
        [$landlord, , $thread, $message] = $this->scenario();

        $this->actingAs($landlord)->patch(route('messages.read', $message))->assertRedirect();

        $pivot = $this->pivot($thread, $landlord);
        $this->assertNotNull($pivot->last_read_at);
        $this->assertTrue($pivot->last_read_at->greaterThanOrEqualTo($message->created_at));

        Event::assertDispatched(
            MessageRead::class,
            fn (MessageRead $e) => $e->threadId === $thread->id && $e->userId === $landlord->id,
        );
    }

    public function test_re_reading_is_idempotent_and_does_not_rebroadcast(): void
    {
        [$landlord, , $thread, $message] = $this->scenario();
        // Already read up to this message.
        $this->pivot($thread, $landlord)->update(['last_read_at' => $message->created_at]);

        Event::fake([MessageRead::class]);
        $this->actingAs($landlord)->patch(route('messages.read', $message))->assertRedirect();

        Event::assertNotDispatched(MessageRead::class);
    }

    public function test_mark_all_read_advances_to_last_message(): void
    {
        Event::fake([MessageRead::class]);
        [$landlord, $tenant, $thread] = $this->scenario();
        $last = $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'second']);

        $this->actingAs($landlord)->post(route('message-threads.read-all', $thread))->assertRedirect();

        $pivot = $this->pivot($thread, $landlord);
        $this->assertTrue($pivot->last_read_at->greaterThanOrEqualTo($last->created_at));
        Event::assertDispatched(MessageRead::class);
    }

    public function test_non_participant_cannot_mark_read(): void
    {
        [$landlord, , , $message] = $this->scenario();
        $outsider = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $this->actingAs($outsider)->patch(route('messages.read', $message))->assertForbidden();
    }

    public function test_read_receipts_exclude_the_viewer(): void
    {
        [$landlord, $tenant, $thread] = $this->scenario();
        $thread->load('participants');

        $receipts = $thread->readReceiptsFor($landlord);

        $this->assertCount(1, $receipts);
        $this->assertSame($tenant->id, $receipts[0]['user_id']);
    }

    public function test_seen_by_boundary_is_inclusive_and_excludes_sender(): void
    {
        [, $tenant, $thread, $message] = $this->scenario();
        // Landlord reads exactly up to the message timestamp.
        $landlordPivot = $thread->participants()->where('users.id', '!=', $tenant->id)->first();
        $this->pivot($thread, $landlordPivot)->update(['last_read_at' => $message->created_at]);

        $thread->load('participants');
        $seen = $message->seenBy($thread->participants);

        // Landlord saw it (cursor == created_at); the tenant sender is excluded.
        $seenIds = array_column($seen, 'user_id');
        $this->assertContains($landlordPivot->id, $seenIds);
        $this->assertNotContains($tenant->id, $seenIds);
    }

    public function test_reading_busts_the_unread_cache(): void
    {
        [$landlord, , , $message] = $this->scenario();
        Cache::put('inbox:unread:'.$landlord->id, 7, 60);

        $this->actingAs($landlord)->patch(route('messages.read', $message))->assertRedirect();

        $this->assertFalse(Cache::has('inbox:unread:'.$landlord->id));
    }

    public function test_non_participant_cannot_mark_all_read(): void
    {
        [$landlord, , $thread] = $this->scenario();
        // A caretaker under the same landlord (so TenantScope resolves the
        // thread) who is NOT a participant — the policy must still 403.
        $outsider = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);

        $this->actingAs($outsider)->post(route('message-threads.read-all', $thread))->assertForbidden();
    }

    public function test_mark_all_read_is_idempotent(): void
    {
        [$landlord, , $thread] = $this->scenario();
        $last = $thread->messages()->latest('created_at')->first();
        $this->pivot($thread, $landlord)->update(['last_read_at' => $last->created_at]);

        Event::fake([MessageRead::class]);
        $this->actingAs($landlord)->post(route('message-threads.read-all', $thread))->assertRedirect();

        Event::assertNotDispatched(MessageRead::class);
    }
}
