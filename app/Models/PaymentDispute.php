<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-85 DISPUTE-1: a chargeback/dispute raised by a card network or gateway
 * against a Payment. Tracked + surfaced to the landlord; never auto-reverses the
 * Payment (disputes can be won).
 */
class PaymentDispute extends Model
{
    use Auditable, HasFactory, TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'payment_id',
        'landlord_id',
        'gateway',
        'gateway_dispute_id',
        'charge_reference',
        'amount',
        'currency',
        'reason',
        'status',
        'opened_at',
        'resolved_at',
        'raw',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'raw' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
