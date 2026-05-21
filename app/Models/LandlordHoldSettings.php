<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-72 HOLD-SETTINGS: a landlord's legal-hold preferences. Resolved through
 * HoldSettingsResolver (override ?? config). No TenantScope — looked up by
 * explicit landlord_id (one row per landlord).
 */
class LandlordHoldSettings extends Model
{
    protected $fillable = [
        'landlord_id',
        'stale_after_days',
        'reminder_cooldown_days',
        'matter_reference_format',
        'reminder_recipients',
        'auto_hold_on_eviction',
    ];

    protected $casts = [
        'stale_after_days' => 'integer',
        'reminder_cooldown_days' => 'integer',
        'reminder_recipients' => 'array',
        'auto_hold_on_eviction' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
