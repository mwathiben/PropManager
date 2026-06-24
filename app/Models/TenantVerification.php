<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantVerification extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'verification_item_id',
        'status',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the lease this verification is for
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Get the verification item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(VerificationItem::class, 'verification_item_id');
    }

    /**
     * Get the user who verified this item
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Check if this verification is complete (verified or rejected)
     */
    public function isComplete(): bool
    {
        return in_array($this->status, ['verified', 'rejected']);
    }

    /**
     * Check if this verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this verification is verified
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending verifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Verified verifications
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}
