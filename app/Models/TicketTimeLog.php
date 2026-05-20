<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-70 JOB-ACTIONS-1: a vendor's labour-time entry on a ticket.
 */
class TicketTimeLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'vendor_id',
        'minutes',
        'note',
        'logged_at',
    ];

    protected $casts = [
        'minutes' => 'integer',
        'logged_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
