<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Phase-64 LEGAL-HOLD-3: a single court-ordered preservation
 * directive on a polymorphic subject (MessageThread today, expand
 * later). Auditable tags every create/release with lawful_basis
 * 'legal_obligation' (Kenya DPA Section 30 / GDPR Article 6(1)(c)).
 */
class LegalHold extends Model
{
    use Auditable;

    protected $fillable = [
        'holdable_type',
        'holdable_id',
        'reason',
        'held_by',
        'held_at',
        'released_at',
        'released_by',
    ];

    protected $casts = [
        'held_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function holdable(): MorphTo
    {
        return $this->morphTo();
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function scopeForSubject(Builder $query, string $type, int $id): Builder
    {
        return $query->where('holdable_type', $type)->where('holdable_id', $id);
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }

    /**
     * Phase-13 DPA-3 / Kenya DPA Section 30 / GDPR Article 6(1)(c):
     * the lawful basis for retention beyond the platform default IS
     * legal-obligation processing. Auditable's buildAuditMetadata
     * picks this up automatically.
     */
    public function getLawfulBasis(): string
    {
        return 'legal_obligation';
    }
}
