<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class TenantMessage extends Model
{
    use TenantScope;

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_SMS = 'sms';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_ACTION_TAKEN = 'action_taken';

    public const STATUS_IGNORED = 'ignored';

    public const ACTION_YES = 'yes';

    public const ACTION_NO = 'no';

    public const ACTION_HELP = 'help';

    public const ACTION_ISSUE = 'issue';

    public const ACTION_PAYMENT = 'payment';

    protected $fillable = [
        'landlord_id',
        'user_id',
        'notification_id',
        'ticket_id',
        'twilio_message_sid',
        'from_number',
        'body',
        'media_urls',
        'source',
        'status',
        'action_type',
        'metadata',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'metadata' => 'array',
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isReply(): bool
    {
        return $this->notification_id !== null;
    }

    public function hasTicket(): bool
    {
        return $this->ticket_id !== null;
    }

    public function isFromWhatsApp(): bool
    {
        return $this->source === self::SOURCE_WHATSAPP;
    }

    public function markAsProcessed(?string $actionType = null): void
    {
        $this->update([
            'status' => $actionType ? self::STATUS_ACTION_TAKEN : self::STATUS_PROCESSED,
            'action_type' => $actionType,
        ]);
    }

    public function linkToTicket(Ticket $ticket): void
    {
        $this->update(['ticket_id' => $ticket->id]);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopeFromPhone($query, string $phone)
    {
        return $query->where('from_number', $phone);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
