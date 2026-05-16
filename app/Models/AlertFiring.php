<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertFiring extends Model
{
    protected $fillable = [
        'alert_key',
        'severity',
        'value',
        'threshold',
        'fired_at',
        'resolved_at',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'acknowledgement_note',
        'metadata',
    ];

    protected $casts = [
        'value' => 'float',
        'threshold' => 'float',
        'fired_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}
