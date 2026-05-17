<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-45 PAY-PLAN-MOD-2: append-only audit row capturing a tenant's
 * request to change the installment schedule of an approved PaymentPlan.
 * The landlord either approves (proposed_installments takes effect) or
 * rejects (the plan stays on its original schedule).
 */
class PaymentPlanModification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'payment_plan_id',
        'requested_by_user_id',
        'original_installments',
        'proposed_installments',
        'status',
        'landlord_response',
        'decided_at',
        'decided_by_user_id',
    ];

    protected $casts = [
        'original_installments' => 'array',
        'proposed_installments' => 'array',
        'decided_at' => 'datetime',
    ];

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
