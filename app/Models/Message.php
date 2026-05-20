<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-63 INBOX-THREAD-2: individual message inside a MessageThread.
 *
 * No TenantScope: isolation inherits from the parent thread via the
 * participants pivot. The Auditable trait writes per-message audit
 * rows tagged with the parent thread's landlord_id (resolved through
 * Auditable::getLandlordId fallback to the authenticated user).
 *
 * `sender_id` NULL identifies system-emitted messages such as
 * "Thread locked by landlord" — these are immutable and cannot be
 * soft-deleted by any user (see canBeDeletedBy).
 */
class Message extends Model
{
    use Auditable;
    use SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_ATTACHMENT = 'attachment';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_SYSTEM,
        self::TYPE_ATTACHMENT,
    ];

    /**
     * Sender soft-delete window (minutes). After this much time has
     * elapsed the sender can no longer retract the message.
     */
    public const DELETE_WINDOW_MINUTES = 5;

    protected $fillable = [
        'thread_id',
        'sender_id',
        'body',
        'message_type',
    ];

    protected $attributes = [
        'message_type' => self::TYPE_TEXT,
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    protected static function booted(): void
    {
        static::created(function (Message $message): void {
            // INBOX-THREAD-2: keep the parent thread's recency cursor
            // coherent so the inbox list sorts by last_message_at.
            // Use a direct update to avoid recursive Auditable writes
            // on the parent on every message insert.
            $thread = $message->thread()->withTrashed()->first();

            if ($thread !== null) {
                $thread->forceFill([
                    'last_message_at' => $message->created_at ?? now(),
                ])->save();
            }
        });
    }

    public function isSystem(): bool
    {
        return $this->message_type === self::TYPE_SYSTEM;
    }

    /**
     * INBOX-MOD-1: sender-initiated soft-delete is gated by a short
     * window. System messages are immutable.
     */
    public function canBeDeletedBy(User $user): bool
    {
        if ($this->sender_id === null) {
            return false;
        }

        if ($this->sender_id !== $user->id) {
            return false;
        }

        if ($this->created_at === null) {
            return false;
        }

        return $this->created_at->greaterThan(
            now()->subMinutes(self::DELETE_WINDOW_MINUTES),
        );
    }

    /**
     * Phase-67 READ-RECEIPTS-2: which of the given participants have read
     * this message (their last_read_at cursor reached its created_at). The
     * sender is excluded — you don't "see" your own message. Boundary is
     * inclusive: a cursor exactly at created_at counts as seen.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $participants  thread participants with the pivot loaded
     * @return list<array{user_id:int, name:string}>
     */
    public function seenBy($participants): array
    {
        if ($this->created_at === null) {
            return [];
        }

        return $participants
            ->filter(fn (User $participant) => $participant->id !== $this->sender_id
                && $participant->pivot?->last_read_at !== null
                && $participant->pivot->last_read_at->greaterThanOrEqualTo($this->created_at))
            ->map(fn (User $participant) => ['user_id' => $participant->id, 'name' => $participant->name])
            ->values()
            ->all();
    }
}
