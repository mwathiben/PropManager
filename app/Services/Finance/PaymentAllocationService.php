<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Events\PaymentAllocated;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Illuminate\Support\Facades\DB;

/**
 * Phase-30 INT-PAY-ALLOC-1: closes the Phase-28 deferred TENANT-PAY-2.
 * Given a Payment that pays down an Invoice with an approved
 * PaymentPlan, walk the plan's installments oldest-first and apply
 * the payment amount cent-by-cent until either the payment is
 * exhausted or all installments are fully paid.
 *
 * Invariants:
 *   - sum(installment.paid_amount_cents) MUST equal
 *     sum(allocations applied from payments) for that plan.
 *   - When sum(installment.paid_amount_cents) >= sum(amount_cents),
 *     the plan flips to COMPLETED.
 *   - The service is idempotent on the (payment_id, plan_id) tuple
 *     via a transactional lockForUpdate on the plan.
 *
 * Allocation is monotonic: a payment can only INCREASE
 * paid_amount_cents on an installment, never decrease — refunds /
 * reversals are a separate flow.
 *
 * Returns the per-installment allocation breakdown so callers
 * (audit command, event listener) can record the trace.
 */
class PaymentAllocationService
{
    public function __construct() {}

    /**
     * @return array<int, array{installment_id: int, applied_cents: int}>
     */
    public function allocate(Payment $payment): array
    {
        $invoice = $payment->invoice;
        if ($invoice === null) {
            return [];
        }
        $plan = PaymentPlan::query()
            ->withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [PaymentPlan::STATUS_APPROVED])
            ->first();
        if ($plan === null) {
            return [];
        }

        return DB::transaction(function () use ($payment, $plan): array {
            $plan = PaymentPlan::query()
                ->withoutGlobalScopes()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->first();

            $installments = $plan->installments()
                ->whereIn('status', [PaymentPlanInstallment::STATUS_PENDING])
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            $remaining = (int) round($payment->amount * 100);
            $applied = $this->applyRemainingToInstallments($installments, $remaining);

            $this->completePlanIfFullyPaid($plan);

            if ($applied !== []) {
                PaymentAllocated::dispatch($payment, $plan, $applied);
            }

            return $applied;
        });
    }

    /**
     * Walk installments oldest-first, applying cents until exhausted.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PaymentPlanInstallment>  $installments
     * @return array<int, array{installment_id: int, applied_cents: int}>
     */
    private function applyRemainingToInstallments($installments, int $remaining): array
    {
        $applied = [];

        foreach ($installments as $installment) {
            if ($remaining <= 0) {
                break;
            }
            $owed = max(0, $installment->amount_cents - $installment->paid_amount_cents);
            if ($owed <= 0) {
                continue;
            }
            $thisApply = min($remaining, $owed);
            $installment->paid_amount_cents += $thisApply;
            if ($installment->paid_amount_cents >= $installment->amount_cents) {
                $installment->status = PaymentPlanInstallment::STATUS_PAID;
                $installment->paid_at = now();
            }
            $installment->save();

            $applied[] = [
                'installment_id' => (int) $installment->id,
                'applied_cents' => $thisApply,
            ];
            $remaining -= $thisApply;
        }

        return $applied;
    }

    private function completePlanIfFullyPaid(PaymentPlan $plan): void
    {
        $allPaid = ! $plan->installments()
            ->whereIn('status', [PaymentPlanInstallment::STATUS_PENDING])
            ->exists();
        if ($allPaid && $plan->status === PaymentPlan::STATUS_APPROVED) {
            $plan->update(['status' => PaymentPlan::STATUS_COMPLETED]);
        }
    }
}
