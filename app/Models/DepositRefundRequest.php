<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-28 TENANT-PAY-3: tenant-initiated deposit refund request.
 * Landlord reviews against the MoveOut deduction model (existing
 * infrastructure) and either approves with a final amount or rejects.
 * TenantScope filters by landlord_id; the explicit tenant_id column
 * lets us cheaply enforce "tenant can only see/edit own requests" in
 * the policy without traversing the lease.
 */
class DepositRefundRequest extends Model
{
    use TenantScope;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const METHOD_MPESA = 'mpesa';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CHEQUE = 'cheque';

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'lease_id',
        'requested_amount_cents',
        'payment_method',
        'payment_details',
        'status',
        'final_amount_cents',
        'rejection_reason',
        'payment_reference',
        'submitted_at',
        'reviewed_at',
        'paid_at',
    ];

    protected $casts = [
        'payment_details' => 'array',
        'requested_amount_cents' => 'integer',
        'final_amount_cents' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
