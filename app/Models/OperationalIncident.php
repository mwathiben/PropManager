<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalIncident extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_MITIGATED = 'mitigated';
    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_INVESTIGATING,
        self::STATUS_MITIGATED,
        self::STATUS_RESOLVED,
    ];

    public const SEV1 = 'sev1';
    public const SEV2 = 'sev2';
    public const SEV3 = 'sev3';
    public const SEV4 = 'sev4';

    public const SEVERITIES = [self::SEV1, self::SEV2, self::SEV3, self::SEV4];

    protected $fillable = [
        'title',
        'severity',
        'status',
        'opened_at',
        'mitigated_at',
        'resolved_at',
        'opened_by_user_id',
        'resolved_by_user_id',
        'affected_services',
        'summary',
        'root_cause',
        'post_mortem_url',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'mitigated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'affected_services' => 'array',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function canMitigate(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_INVESTIGATING], true);
    }

    public function canResolve(): bool
    {
        return $this->status !== self::STATUS_RESOLVED;
    }
}
