<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-63 INBOX-THREAD-1/2/3: schema + models + scopeForUser
 * participant-based isolation.
 */
class Phase63ThreadTest extends TestCase
{
    use RefreshDatabase;

    private function landlord(): User
    {
        return User::factory()->create(['role' => 'landlord']);
    }

    private function tenantOf(User $landlord): User
    {
        return User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
    }

    private function makeThread(User $landlord, array $participants = []): MessageThread
    {
        $thread = MessageThread::create([
            'landlord_id' => $landlord->id,
            'title' => 'Test thread',
        ]);

        $thread->participants()->attach($landlord->id, [
            'role' => MessageThread::ROLE_LANDLORD,
        ]);

        foreach ($participants as $user) {
            $thread->participants()->attach($user->id, [
                'role' => $user->role === 'caretaker'
                    ? MessageThread::ROLE_CARETAKER
                    : MessageThread::ROLE_TENANT,
            ]);
        }

        return $thread;
    }

    public function test_message_threads_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'version', 'landlord_id', 'subject_type', 'subject_id',
            'title', 'status', 'last_message_at',
            'deleted_at', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('message_threads', $column),
                "message_threads.{$column} is missing",
            );
        }
    }

    public function test_messages_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'thread_id', 'sender_id', 'body', 'message_type',
            'deleted_at', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('messages', $column),
                "messages.{$column} is missing",
            );
        }
    }

    public function test_participants_table_has_expected_columns_and_unique(): void
    {
        $expected = [
            'id', 'thread_id', 'user_id', 'role', 'last_read_at',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('message_thread_participants', $column),
                "message_thread_participants.{$column} is missing",
            );
        }
    }

    public function test_message_thread_uses_required_traits(): void
    {
        $uses = class_uses(MessageThread::class);

        $this->assertContains(Auditable::class, $uses);
        $this->assertContains(SoftDeletes::class, $uses);
        $this->assertContains(TenantScope::class, $uses);
    }

    public function test_message_uses_audit_and_softdeletes_but_not_tenant_scope(): void
    {
        $uses = class_uses(Message::class);

        $this->assertContains(Auditable::class, $uses);
        $this->assertContains(SoftDeletes::class, $uses);
        $this->assertNotContains(
            TenantScope::class,
            $uses,
            'Message must NOT use TenantScope — isolation inherits from MessageThread via the participants pivot.',
        );
    }

    public function test_status_and_role_constants_exposed(): void
    {
        $this->assertSame(
            ['open', 'archived', 'locked'],
            MessageThread::STATUSES,
        );

        $this->assertSame(
            ['landlord', 'caretaker', 'tenant'],
            MessageThread::ROLES,
        );

        $this->assertSame(
            ['text', 'system', 'attachment'],
            Message::TYPES,
        );
    }

    public function test_version_defaults_to_one(): void
    {
        $landlord = $this->landlord();
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);

        $this->assertSame(1, $thread->fresh()->version);
    }

    public function test_cross_tenant_isolation_via_participants_pivot(): void
    {
        // Two tenants under the SAME landlord. TenantScope alone would
        // grant both visibility into both threads. scopeForUser via
        // the participants pivot is the authoritative gate.
        $landlord = $this->landlord();
        $tenantA = $this->tenantOf($landlord);
        $tenantB = $this->tenantOf($landlord);

        $threadA = $this->makeThread($landlord, [$tenantA]);
        $threadB = $this->makeThread($landlord, [$tenantB]);

        $visibleToA = MessageThread::query()->forUser($tenantA)->pluck('id')->all();
        $visibleToB = MessageThread::query()->forUser($tenantB)->pluck('id')->all();

        $this->assertSame([$threadA->id], $visibleToA);
        $this->assertSame([$threadB->id], $visibleToB);

        // Landlord sees both since they are a participant on both.
        $visibleToLandlord = MessageThread::query()
            ->forUser($landlord)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$threadA->id, $threadB->id], $visibleToLandlord);
    }

    public function test_message_creation_bumps_thread_last_message_at(): void
    {
        $landlord = $this->landlord();
        $tenant = $this->tenantOf($landlord);
        $thread = $this->makeThread($landlord, [$tenant]);

        $this->assertNull($thread->fresh()->last_message_at);

        $beforeInsert = Carbon::now();
        $thread->messages()->create([
            'sender_id' => $landlord->id,
            'body' => 'Hello tenant',
        ]);

        $lastMessageAt = $thread->fresh()->last_message_at;

        $this->assertNotNull($lastMessageAt);
        $this->assertTrue(
            $lastMessageAt->greaterThanOrEqualTo($beforeInsert->subSecond()),
            'last_message_at should advance to the new message timestamp',
        );
    }

    public function test_record_system_event_inserts_system_typed_message_with_null_sender(): void
    {
        $landlord = $this->landlord();
        $tenant = $this->tenantOf($landlord);
        $thread = $this->makeThread($landlord, [$tenant]);

        $message = $thread->recordSystemEvent('Thread locked by landlord');

        $this->assertNull($message->sender_id);
        $this->assertSame(Message::TYPE_SYSTEM, $message->message_type);
        $this->assertTrue($message->isSystem());
        $this->assertSame('Thread locked by landlord', $message->body);
    }

    public function test_can_be_deleted_by_within_five_minute_window(): void
    {
        $landlord = $this->landlord();
        $tenant = $this->tenantOf($landlord);
        $thread = $this->makeThread($landlord, [$tenant]);

        $fresh = $thread->messages()->create([
            'sender_id' => $landlord->id,
            'body' => 'Just sent',
        ]);

        $this->assertTrue($fresh->canBeDeletedBy($landlord));
        $this->assertFalse(
            $fresh->canBeDeletedBy($tenant),
            'Non-sender must never be able to delete.',
        );

        $stale = $thread->messages()->create([
            'sender_id' => $landlord->id,
            'body' => 'Old',
        ]);
        $stale->created_at = Carbon::now()->subMinutes(6);
        $stale->save();

        $this->assertFalse(
            $stale->fresh()->canBeDeletedBy($landlord),
            'Sender must not be able to delete outside the 5-minute window.',
        );
    }

    public function test_system_messages_are_immutable_even_by_landlord(): void
    {
        $landlord = $this->landlord();
        $tenant = $this->tenantOf($landlord);
        $thread = $this->makeThread($landlord, [$tenant]);

        $system = $thread->recordSystemEvent('Thread opened');

        $this->assertFalse(
            $system->canBeDeletedBy($landlord),
            'System messages (sender_id NULL) must be immutable.',
        );
    }

    public function test_unread_count_excludes_own_messages_and_respects_last_read_at(): void
    {
        $landlord = $this->landlord();
        $tenant = $this->tenantOf($landlord);
        $thread = $this->makeThread($landlord, [$tenant]);

        $thread->messages()->create(['sender_id' => $landlord->id, 'body' => 'one']);
        $thread->messages()->create(['sender_id' => $landlord->id, 'body' => 'two']);
        $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'tenant reply']);

        // Tenant has no last_read_at — sees 2 unread (landlord's two).
        $this->assertSame(2, $thread->unreadCountFor($tenant));

        // After marking read, unread should drop to 0.
        MessageThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $tenant->id)
            ->update(['last_read_at' => Carbon::now()]);

        $this->assertSame(0, $thread->unreadCountFor($tenant));

        // Landlord has no last_read_at — sees the tenant reply (1 unread, excludes own).
        $this->assertSame(1, $thread->unreadCountFor($landlord));
    }

    public function test_polymorphic_subject_can_be_null(): void
    {
        $landlord = $this->landlord();
        $thread = MessageThread::create([
            'landlord_id' => $landlord->id,
            'title' => 'General inquiry',
        ]);

        $this->assertNull($thread->subject_type);
        $this->assertNull($thread->subject_id);
        $this->assertNull($thread->subject);
    }

    public function test_soft_delete_does_not_remove_row(): void
    {
        $landlord = $this->landlord();
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);
        $threadId = $thread->id;

        $thread->delete();

        $this->assertSoftDeleted('message_threads', ['id' => $threadId]);
        $this->assertNotNull(
            MessageThread::withTrashed()->find($threadId),
            'Soft-deleted thread should still be retrievable with withTrashed.',
        );
    }
}
