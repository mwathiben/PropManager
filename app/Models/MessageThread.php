<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Phase-63 INBOX-THREAD-1: bi-directional landlord<->tenant message
 * thread. Polymorphic `subject` attaches the thread to a Lease, Ticket,
 * or stands alone.
 *
 * Cross-tenant isolation lives in the message_thread_participants
 * pivot, NOT in TenantScope: two tenants under the same landlord_id
 * must not enumerate each other's threads. Every inbox query routes
 * through scopeForUser() which joins the pivot.
 */
class MessageThread extends Model
{
    use Auditable;
    use SoftDeletes;
    use TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ARCHIVED,
        self::STATUS_LOCKED,
    ];

    public const ROLE_LANDLORD = 'landlord';

    public const ROLE_CARETAKER = 'caretaker';

    public const ROLE_TENANT = 'tenant';

    public const ROLES = [
        self::ROLE_LANDLORD,
        self::ROLE_CARETAKER,
        self::ROLE_TENANT,
    ];

    protected $fillable = [
        'landlord_id',
        'subject_type',
        'subject_id',
        'title',
        'status',
        'last_message_at',
        'version',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'version' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'version' => 1,
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_thread_participants', 'thread_id', 'user_id')
            ->using(MessageThreadParticipant::class)
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function scopeOpenOnly(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForLandlord(Builder $query, int $landlordId): Builder
    {
        return $query->where('landlord_id', $landlordId);
    }

    /**
     * Authoritative isolation boundary for inbox visibility.
     *
     * The participant pivot — NOT landlord_id — gates which threads
     * a given user can see. Two tenants under the same landlord
     * cannot enumerate each other's threads because they only
     * appear in their own participant rows.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $q) => $q->where('users.id', $user->id),
        );
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function recordSystemEvent(string $body): Message
    {
        return $this->messages()->create([
            'sender_id' => null,
            'body' => $body,
            'message_type' => Message::TYPE_SYSTEM,
        ]);
    }

    public function unreadCountFor(User $user): int
    {
        $row = DB::table('message_thread_participants')
            ->where('thread_id', $this->id)
            ->where('user_id', $user->id)
            ->first();

        if ($row === null) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $user->id);

        if ($row->last_read_at !== null) {
            $query->where('created_at', '>', $row->last_read_at);
        }

        return $query->count();
    }
}
