<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class TicketActivity extends Model
{
    use TenantScope;

    public $timestamps = false;

    protected $fillable = [
        'landlord_id',
        'ticket_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'description',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Phase-28 TENANT-MAINT-1: when a landlord or caretaker posts the
     * first activity (other than the tenant's own 'created' event),
     * stamp the parent ticket's first_response_at. Cheap noop when the
     * ticket already has first_response_at set or when the actor is the
     * tenant reporter — keeps the SLA breach detector accurate.
     */
    protected static function booted(): void
    {
        static::created(function (self $activity) {
            $actor = \App\Models\User::find($activity->user_id);
            if (! $actor || $actor->isTenant()) {
                return;
            }
            if ($activity->action === self::ACTION_CREATED) {
                return;
            }
            $ticket = $activity->ticket()->first();
            if ($ticket && $ticket->first_response_at === null) {
                $ticket->forceFill(['first_response_at' => $activity->created_at ?? now()])->save();
            }
        });
    }

    // --- RELATIONSHIPS ---

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // --- ACTIVITY TYPES ---

    public const ACTION_CREATED = 'created';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_COMMENTED = 'commented';

    public const ACTION_RESOLVED = 'resolved';

    public const ACTION_CLOSED = 'closed';

    public const ACTION_FEEDBACK_SUBMITTED = 'feedback_submitted';

    // Phase-80 ESCALATION: caretaker→landlord escalation lifecycle.
    public const ACTION_ESCALATED = 'escalated';

    public const ACTION_ESCALATION_ACKNOWLEDGED = 'escalation_acknowledged';

    // --- HELPERS ---

    public function isSystemGenerated(): bool
    {
        return is_null($this->user_id);
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Ticket created',
            self::ACTION_STATUS_CHANGED => 'Status changed',
            self::ACTION_ASSIGNED => 'Assigned',
            self::ACTION_COMMENTED => 'Comment added',
            self::ACTION_RESOLVED => 'Marked as resolved',
            self::ACTION_CLOSED => 'Ticket closed',
            self::ACTION_FEEDBACK_SUBMITTED => 'Feedback submitted',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function getIcon(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'plus-circle',
            self::ACTION_STATUS_CHANGED => 'arrow-path',
            self::ACTION_ASSIGNED => 'user-plus',
            self::ACTION_COMMENTED => 'chat-bubble-left',
            self::ACTION_RESOLVED => 'check-circle',
            self::ACTION_CLOSED => 'lock-closed',
            self::ACTION_FEEDBACK_SUBMITTED => 'star',
            default => 'information-circle',
        };
    }

    public function getColor(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'blue',
            self::ACTION_STATUS_CHANGED => 'purple',
            self::ACTION_ASSIGNED => 'indigo',
            self::ACTION_COMMENTED => 'gray',
            self::ACTION_RESOLVED => 'green',
            self::ACTION_CLOSED => 'gray',
            self::ACTION_FEEDBACK_SUBMITTED => 'yellow',
            default => 'gray',
        };
    }
}
