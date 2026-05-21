<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-71 REACTIONS: a single emoji reaction by one user on one message.
 *
 * No TenantScope — isolation inherits from the parent message's thread via
 * the participants pivot (the toggle endpoint gates on MessageThreadPolicy).
 */
class MessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
