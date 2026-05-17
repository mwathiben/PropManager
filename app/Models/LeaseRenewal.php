<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-29 WF-LEASE-RENEW-2: one row per renewal cycle on a lease.
 * Status machine: proposed → accepted | rejected → confirmed; expired
 * is a terminal state set by a future janitor when the proposed
 * end_date passes without confirmation.
 */
class LeaseRenewal extends Model
{
    use TenantScope;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_COUNTER_PROPOSED = 'counter_proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_EXPIRED = 'expired';

    public const OPEN_STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_COUNTER_PROPOSED,
        self::STATUS_ACCEPTED,
    ];

    /** Phase-45 LEASE-COUNTER-3: counter-offers older than this expire. */
    public const COUNTER_EXPIRY_DAYS = 14;

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'proposed_end_date',
        'proposed_rent_amount_cents',
        'status',
        'notes',
        'rejection_reason',
        'counter_rent_amount_cents',
        'counter_end_date',
        'counter_message',
        'counter_submitted_at',
        'proposed_at',
        'responded_at',
        'confirmed_at',
    ];

    protected $casts = [
        'proposed_end_date' => 'date',
        'proposed_rent_amount_cents' => 'integer',
        'counter_rent_amount_cents' => 'integer',
        'counter_end_date' => 'date',
        'counter_submitted_at' => 'datetime',
        'proposed_at' => 'datetime',
        'responded_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function history()
    {
        return $this->hasMany(LeaseRenewalCounterHistory::class)->orderBy('created_at');
    }
}
