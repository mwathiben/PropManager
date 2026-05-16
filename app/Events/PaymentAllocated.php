<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use App\Models\PaymentPlan;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase-30 INT-PAY-ALLOC-3: fired by PaymentAllocationService when a
 * payment is applied to a plan's installments. $applied is the
 * list<{installment_id, applied_cents}> breakdown.
 */
class PaymentAllocated
{
    use Dispatchable;

    /**
     * @param  list<array{installment_id: int, applied_cents: int}>  $applied
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentPlan $plan,
        public readonly array $applied,
    ) {}
}
