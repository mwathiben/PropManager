<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankReconciliationQueue;
use App\Models\Invoice;
use Carbon\CarbonInterface;

/**
 * Phase-81 PERIOD-CLOSE-1: pre-close readiness check. Closing a month locks it
 * (EnforcesAccountingPeriodLock blocks writes), so a landlord should not lock a
 * period that still has unfinished work — draft invoices dated in the period or
 * pending/unmatched bank-reconciliation items in the period. Hard blockers stop
 * the close unless forced (audited).
 */
class PeriodCloseReadinessService
{
    /**
     * @return array{draft_invoices:int, pending_reconciliation:int, ready:bool}
     */
    public function check(int $landlordId, CarbonInterface $start, CarbonInterface $end): array
    {
        $draftInvoices = Invoice::query()
            ->where('landlord_id', $landlordId)
            ->where('status', 'draft')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();

        $pendingReconciliation = BankReconciliationQueue::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('status', ['pending', 'unmatched', 'error'])
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();

        return [
            'draft_invoices' => $draftInvoices,
            'pending_reconciliation' => $pendingReconciliation,
            'ready' => $draftInvoices === 0 && $pendingReconciliation === 0,
        ];
    }
}
