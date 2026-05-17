<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-45 LEASE-COUNTER-3: append-only audit row for every status
 * transition on a LeaseRenewal. Each row captures the rent + end_date
 * + message in effect at the moment of the action; the negotiation
 * history is reconstructable from these rows.
 */
class LeaseRenewalCounterHistory extends Model
{
    protected $table = 'lease_renewal_counter_history';

    public const ACTION_PROPOSED = 'proposed';

    public const ACTION_COUNTERED = 'countered';

    public const ACTION_RE_PROPOSED = 're_proposed';

    public const ACTION_ACCEPTED = 'accepted';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_EXPIRED = 'expired';

    protected $fillable = [
        'lease_renewal_id',
        'actor_user_id',
        'action',
        'rent_amount_cents',
        'end_date',
        'message',
    ];

    protected $casts = [
        'rent_amount_cents' => 'integer',
        'end_date' => 'date',
    ];

    public function leaseRenewal(): BelongsTo
    {
        return $this->belongsTo(LeaseRenewal::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
