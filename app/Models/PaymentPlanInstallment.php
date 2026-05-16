<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-28 TENANT-PAY-1: one installment within a PaymentPlan.
 * Tenant scoping inherits via the parent plan (PaymentPlanPolicy gates
 * cross-tenant access on the plan, not the installment row).
 */
class PaymentPlanInstallment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_DEFAULTED = 'defaulted';

    protected $fillable = [
        'payment_plan_id',
        'due_date',
        'amount_cents',
        'paid_amount_cents',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount_cents' => 'integer',
        'paid_amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount_cents >= $this->amount_cents;
    }
}
