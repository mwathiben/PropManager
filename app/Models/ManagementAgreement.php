<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementStatus;
use App\Enums\ClauseBinding;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Slice-2: a management agreement between an owner and the managing account
 * (landlord OR manager). Composed of clause instances; rendered_body +
 * content_hash are the canonical signed snapshot. The fee clause is the one
 * bound to PropertyOwner.management_fee_* — wired + locked on activation in
 * PR 2.3.
 */
class ManagementAgreement extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\ManagementAgreementFactory> */
    use HasFactory;

    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'property_owner_id',
        'status',
        'title',
        'rendered_body',
        'content_hash',
        'effective_date',
        'sent_at',
        'signed_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgreementStatus::class,
            'effective_date' => 'date',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    /** @return HasMany<AgreementClause> */
    public function agreementClauses(): HasMany
    {
        return $this->hasMany(AgreementClause::class)->orderBy('position');
    }

    /**
     * The clause instance bound to PropertyOwner.management_fee_* — what the
     * AgreementApplicator reads to write + lock the fee on activation (PR 2.3).
     */
    public function feeClause(): ?AgreementClause
    {
        return $this->agreementClauses()
            ->whereHas('clause', fn (Builder $query) => $query->where('binding', ClauseBinding::ManagementFee))
            ->with('clause')
            ->orderBy('id')
            ->first();
    }

    /**
     * Snapshot the composed clauses into the canonical signed text and bind it
     * with a SHA-256 content hash (tamper-evidence for the assent record).
     * Refuses once signed — the snapshot a signature points at is immutable;
     * changes go through an amendment (PR 2.3).
     */
    public function recomputeRenderedBody(): void
    {
        if ($this->status->isLocked()) {
            throw new \RuntimeException('A signed agreement cannot be re-rendered; amend it instead.');
        }

        $body = $this->agreementClauses()
            ->with('clause')
            ->get()
            ->map(fn (AgreementClause $instance) => $instance->render())
            ->implode("\n\n");

        $this->rendered_body = $body;
        $this->content_hash = hash('sha256', $body);
        $this->save();
    }
}
