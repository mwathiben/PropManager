<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentAllocated;
use App\Services\WorkflowLogger;

/**
 * Phase-30 INT-PAY-ALLOC-3: append-only audit row for each allocation
 * pass — feeds the WorkflowRunLog used by workflow:health silent-
 * failure detection.
 */
class LogPaymentAllocated
{
    public function __construct(
        private readonly WorkflowLogger $logger,
    ) {}

    public function handle(PaymentAllocated $event): void
    {
        $this->logger->log(
            workflowName: 'payment-plan:allocate',
            action: 'allocated',
            landlordId: (int) $event->plan->landlord_id,
            targetType: \App\Models\PaymentPlan::class,
            targetId: (int) $event->plan->id,
            metadata: [
                'payment_id' => $event->payment->id,
                'allocations' => $event->applied,
            ],
        );
    }
}
