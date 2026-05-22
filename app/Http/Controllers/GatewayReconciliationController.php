<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RefundStatus;
use App\Http\Traits\WithLandlordScope;
use App\Models\PaymentDispute;
use App\Models\ReconciliationReport;
use App\Models\Refund;
use Inertia\Inertia;

/**
 * Phase-85 RECON-VIEW: in-app surface for the gateway reconciliation reports the
 * DailyPaymentReconciliation cron persists. Before this the discrepancies were
 * only emailed (ReconciliationAlert) — a landlord could not see or act on them.
 * Read-only over ReconciliationReport (gateway recon), distinct from the Phase-81
 * BANK reconciliation UI.
 */
class GatewayReconciliationController extends Controller
{
    use WithLandlordScope;

    public function index()
    {
        $landlordId = $this->getLandlordId();

        $reports = ReconciliationReport::where('landlord_id', $landlordId)
            ->orderByDesc('reconciled_at')
            ->paginate(20)
            ->through(fn (ReconciliationReport $r) => [
                'id' => $r->id,
                'provider' => $r->provider,
                'status' => $r->status,
                'period_from' => $r->period_from?->toDateString(),
                'period_to' => $r->period_to?->toDateString(),
                'local_count' => $r->local_count,
                'remote_count' => $r->remote_count,
                'matched_count' => $r->matched_count,
                'discrepancy_count' => $r->discrepancy_count,
                'reconciled_at' => $r->reconciled_at?->toDateTimeString(),
            ]);

        // Phase-85 DISPUTE-2 + REFUND-RETRY-3: surface open disputes + failed
        // refunds needing attention alongside the reconciliation reports.
        $disputes = PaymentDispute::where('landlord_id', $landlordId)
            ->whereIn('status', [PaymentDispute::STATUS_OPEN, PaymentDispute::STATUS_UNDER_REVIEW])
            ->orderByDesc('opened_at')
            ->limit(20)
            ->get()
            ->map(fn (PaymentDispute $d) => [
                'id' => $d->id,
                'gateway' => $d->gateway,
                'amount' => $d->amount,
                'currency' => $d->currency,
                'reason' => $d->reason,
                'status' => $d->status,
                'opened_at' => $d->opened_at?->toDateString(),
            ])
            ->all();

        $failedRefunds = Refund::where('landlord_id', $landlordId)
            ->where('status', RefundStatus::Failed)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Refund $r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'payment_method' => $r->payment_method,
                'retry_count' => $r->retry_count,
                'needs_review' => $r->needs_review,
            ])
            ->all();

        return Inertia::render('Finances/GatewayReconciliation/Index', [
            'reports' => $reports,
            'disputes' => $disputes,
            'failedRefunds' => $failedRefunds,
        ]);
    }

    public function show(ReconciliationReport $report)
    {
        abort_unless((int) $report->landlord_id === $this->getLandlordId(), 403);

        return Inertia::render('Finances/GatewayReconciliation/Show', [
            'report' => [
                'id' => $report->id,
                'provider' => $report->provider,
                'status' => $report->status,
                'period_from' => $report->period_from?->toDateString(),
                'period_to' => $report->period_to?->toDateString(),
                'local_count' => $report->local_count,
                'remote_count' => $report->remote_count,
                'matched_count' => $report->matched_count,
                'discrepancy_count' => $report->discrepancy_count,
                'error_message' => $report->error_message,
                'reconciled_at' => $report->reconciled_at?->toDateTimeString(),
                'discrepancies' => $report->discrepancies,
            ],
        ]);
    }
}
