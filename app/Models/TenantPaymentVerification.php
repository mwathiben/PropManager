<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TenantPaymentVerification extends Model
{
    use HasFactory, TenantScope;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_SUBMITTED = 'payment_submitted';

    public const STATUS_PAYMENT_VERIFIED = 'payment_verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'status',
        'deposit_required',
        'first_rent_required',
        'other_charges',
        'other_charges_description',
        'total_required',
        'amount_paid',
        'rejection_reason',
        'submitted_at',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'deposit_required' => 'decimal:2',
        'first_rent_required' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_required' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_PAYMENT_SUBMITTED;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_PAYMENT_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function needsReview(): bool
    {
        return $this->status === self::STATUS_PAYMENT_SUBMITTED;
    }

    public function getOutstandingAmount(): float
    {
        return max(0, $this->total_required - $this->amount_paid);
    }

    public function isFullyPaid(): bool
    {
        return $this->amount_paid >= $this->total_required;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_PAYMENT_SUBMITTED);
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('status', self::STATUS_PAYMENT_SUBMITTED);
    }

    public function scopeNotVerified($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PAYMENT_SUBMITTED,
            self::STATUS_REJECTED,
        ]);
    }

    public function markAsSubmitted(): void
    {
        $this->update([
            'status' => self::STATUS_PAYMENT_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function approve(?int $verifierId): void
    {
        $this->update([
            'status' => self::STATUS_PAYMENT_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifierId,
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason, int $verifierId): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_by' => $verifierId,
        ]);
    }

    public function resetForResubmission(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING_PAYMENT,
            'rejection_reason' => null,
            'submitted_at' => null,
        ]);
    }

    public function recordPayment(float $amount): void
    {
        $this->increment('amount_paid', $amount);
        $this->refresh();
    }
}
