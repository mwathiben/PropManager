<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Phase-29 WF-LATE-FEE-2 + WF-VACANCY-2: landlord-facing task board.
 * Polymorphic relatedTo lets tasks point at Invoices (late-fee call
 * tasks), Units (vacancy list tasks), Leases, etc.
 */
class LandlordTask extends Model
{
    use TenantScope;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_SNOOZED = 'snoozed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_DISMISSED,
        self::STATUS_SNOOZED,
    ];

    protected $fillable = [
        'landlord_id',
        'assigned_to_user_id',
        'task_type',
        'related_to_id',
        'related_to_type',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'snoozed_until',
        'completed_at',
        'dismissed_reason',
        'source_workflow',
    ];

    protected $casts = [
        'due_date' => 'date',
        'snoozed_until' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function relatedTo(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
