<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentPlan;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-30 INT-PAY-ALLOC-2: nightly drift detector for the
 * PaymentAllocationService invariant. For every APPROVED or
 * COMPLETED plan, sum the installments and check that the plan's
 * total_amount_cents matches the sum of amount_cents AND that the
 * status reflects reality (a plan with every installment paid
 * should be COMPLETED, not APPROVED).
 *
 * Same shape as Phase-17 MONEY-5 / Phase-18 DATA-2 / Phase-19 INDEX-1
 * drift audits: log mismatches, emit a Prometheus gauge, return
 * SUCCESS even on drift so the schedule wrapper doesn't email-spam
 * (the gauge is the signal).
 */
class PaymentPlanAllocationsAudit extends Command
{
    protected $signature = 'payment-plan-allocations:audit';

    protected $description = 'Phase-30 INT-PAY-ALLOC-2: plan-vs-installment drift audit.';

    public function handle(MetricsService $metrics): int
    {
        $totalMismatch = 0;
        $statusMismatch = 0;

        PaymentPlan::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [PaymentPlan::STATUS_APPROVED, PaymentPlan::STATUS_COMPLETED])
            ->with('installments')
            ->cursor()
            ->each(function (PaymentPlan $plan) use (&$totalMismatch, &$statusMismatch): void {
                $sumOwed = (int) $plan->installments->sum('amount_cents');
                $sumPaid = (int) $plan->installments->sum('paid_amount_cents');

                if ($sumOwed !== (int) $plan->total_amount_cents) {
                    $totalMismatch++;
                    \Log::warning('PaymentPlan total drift', [
                        'plan_id' => $plan->id,
                        'plan_total_cents' => $plan->total_amount_cents,
                        'installments_sum_cents' => $sumOwed,
                    ]);
                }

                $shouldBeCompleted = $sumOwed > 0 && $sumPaid >= $sumOwed;
                if ($shouldBeCompleted && $plan->status !== PaymentPlan::STATUS_COMPLETED) {
                    $statusMismatch++;
                    \Log::warning('PaymentPlan status drift — fully paid but status is APPROVED', [
                        'plan_id' => $plan->id,
                    ]);
                }
            });

        $metrics->gauge('payment_plan_allocation_total_drift_count', (float) $totalMismatch);
        $metrics->gauge('payment_plan_allocation_status_drift_count', (float) $statusMismatch);

        $this->info("Drift summary: total={$totalMismatch}, status={$statusMismatch}");

        return self::SUCCESS;
    }
}
