<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-72 MATTER-GROUPING: a legal "case" grouping many holds. Landlord-scoped
 * via TenantScope (landlord_id auto-set on create); Auditable logs the open/
 * close lifecycle. Holds link via legal_holds.legal_matter_id (nullable).
 */
class LegalMatter extends Model
{
    use Auditable;
    use TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'landlord_id',
        'title',
        'matter_reference',
        'situation_type',
        'status',
        'review_by',
        'description',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'review_by' => 'date',
        'closed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    public function holds(): HasMany
    {
        return $this->hasMany(LegalHold::class);
    }

    /** Active (unreleased) holds in this matter. */
    public function activeHolds(): HasMany
    {
        return $this->hasMany(LegalHold::class)->whereNull('released_at');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /** Open matters whose review-by date has arrived (or passed). */
    public function scopeReviewDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->whereNotNull('review_by')
            ->whereDate('review_by', '<=', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isReviewDue(): bool
    {
        return $this->isOpen()
            && $this->review_by !== null
            && $this->review_by->lessThanOrEqualTo(now());
    }

    public function close(User $by): void
    {
        $this->forceFill([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $by->id,
        ])->save();
    }

    public function reopen(): void
    {
        $this->forceFill([
            'status' => self::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();
    }
}
