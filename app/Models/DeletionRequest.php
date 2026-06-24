<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    use HasFactory;

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'reason',
        'status',
        'requested_at',
        'scheduled_deletion_at',
        'completed_at',
        'cancelled_at',
        'anonymized_email',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user who requested deletion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if request was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get days remaining until deletion.
     */
    public function getDaysRemainingAttribute(): int
    {
        if (! $this->isPending()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->scheduled_deletion_at, false));
    }

    /**
     * Scope: Pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Ready for processing (past grace period).
     */
    public function scopeReadyForProcessing($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_deletion_at', '<=', now());
    }
}
