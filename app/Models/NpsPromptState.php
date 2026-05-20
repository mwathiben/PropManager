<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-66 NPS-SURVEY-2: server-side prompt cadence state, one row per
 * user. Deliberately NOT TenantScope'd — it is keyed and queried by
 * user_id directly (NpsEligibilityService), independent of landlord
 * tenancy.
 */
class NpsPromptState extends Model
{
    protected $fillable = [
        'user_id',
        'last_prompted_at',
        'last_responded_at',
        'dismiss_count',
        'opted_out_at',
        'snoozed_until',
    ];

    protected $casts = [
        'last_prompted_at' => 'datetime',
        'last_responded_at' => 'datetime',
        'dismiss_count' => 'integer',
        'opted_out_at' => 'datetime',
        'snoozed_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
