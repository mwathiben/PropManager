<?php

namespace App\Models;

use App\Enums\KycSubmissionStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $landlord_id
 * @property int $requirement_id
 * @property int|null $document_id
 * @property string|null $submission_value
 * @property KycSubmissionStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 * @property \Carbon\Carbon|null $submitted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $tenant
 * @property-read User $landlord
 * @property-read KycRequirement $requirement
 * @property-read Document|null $document
 * @property-read User|null $reviewer
 */
class TenantKycSubmission extends Model
{
    use Auditable, HasFactory, TenantScope;

    /**
     * Phase-13 DPA-3: KYC processing is a legal obligation under
     * Kenya's AML/CFT regulations and the Proceeds of Crime and
     * Anti-Money Laundering Act. national_id and biometric data fall
     * under SENSITIVE_DATA_CATEGORIES — the lawful basis matters when
     * a subject objects (DPA-5) because legal_obligation overrides
     * objection.
     */
    public function getLawfulBasis(): string
    {
        return 'legal_obligation';
    }

    protected $fillable = [
        'user_id',
        'landlord_id',
        'requirement_id',
        'document_id',
        'submission_value',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'status' => KycSubmissionStatus::class,
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(KycRequirement::class, 'requirement_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to filter pending submissions.
     *
     * @param  Builder<TenantKycSubmission>  $query
     * @return Builder<TenantKycSubmission>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', KycSubmissionStatus::Pending);
    }

    /**
     * Scope to filter approved submissions.
     *
     * @param  Builder<TenantKycSubmission>  $query
     * @return Builder<TenantKycSubmission>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', KycSubmissionStatus::Approved);
    }

    /**
     * Scope to filter rejected submissions.
     *
     * @param  Builder<TenantKycSubmission>  $query
     * @return Builder<TenantKycSubmission>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', KycSubmissionStatus::Rejected);
    }

    /**
     * Check if submission is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === KycSubmissionStatus::Pending;
    }

    /**
     * Check if submission is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === KycSubmissionStatus::Approved;
    }

    /**
     * Check if submission is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === KycSubmissionStatus::Rejected;
    }
}
