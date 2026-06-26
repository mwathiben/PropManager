<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ForfeitDepositRequest;
use App\Http\Requests\Finance\RefundDepositRequest;
use App\Http\Traits\WithETag;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Mail\DepositRefundNotification;
use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Services\FinanceExportService;
use App\Services\FinanceFilterService;
use App\Services\FinanceStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DepositController extends Controller
{
    use WithETag;
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceStatsService $statsService,
        protected FinanceFilterService $filterService,
        protected FinanceExportService $exportService,
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('deposits', [
            'deposits' => $this->filterService->getPaginatedDeposits($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'building_id']),
            'stats' => $this->statsService->getDepositStats($landlordId),
        ]);
    }

    public function refund(RefundDepositRequest $request, Lease $lease): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($lease->deposit_status !== 'held') {
            return back()->withErrors(['error' => 'This deposit has already been processed.']);
        }

        $refundAmount = $request->refund_amount;
        $deductions = $request->deductions ?? 0;

        if (($refundAmount + $deductions) > $lease->deposit_amount) {
            return back()->withErrors(['error' => 'Refund amount plus deductions cannot exceed deposit amount.']);
        }

        $status = $deductions > 0 ? 'partial_refund' : 'refunded';
        $previousDepositStatus = $lease->deposit_status;

        $lease->update([
            'deposit_status' => $status,
            'deposit_refund_amount' => $refundAmount,
            'deposit_deductions' => $deductions,
            'deposit_deduction_reason' => $request->deduction_reason,
            'deposit_processed_at' => now(),
            'deposit_processed_by' => auth()->id(),
        ]);

        // AUDIT-6: emit a status_changed event so the deposit refund is
        // reconstructable from AuditLog with the deduction reason intact.
        $lease->logStatusChange(
            "deposit:{$previousDepositStatus}",
            "deposit:{$status}",
            $request->deduction_reason ?? 'Deposit refund',
        );

        $this->recordDeductionTransactionIfNeeded($lease, $landlordId, [
            'deductions' => $deductions,
            'deduction_reason' => $request->deduction_reason,
            'notes' => $request->notes,
        ]);

        $this->recordRefundTransaction($lease, $landlordId, $request);

        $this->notifyTenantOfRefund($lease, $status);

        return back()->with('success', 'Deposit refund processed successfully.');
    }

    private function recordDeductionTransactionIfNeeded(Lease $lease, int $landlordId, array $data): void
    {
        if ($data['deductions'] <= 0) {
            return;
        }

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlordId,
            'processed_by' => auth()->id(),
            'type' => DepositTransaction::TYPE_DEDUCTION,
            'amount' => $data['deductions'],
            'balance_after' => $lease->deposit_amount - $data['deductions'],
            'reason' => $data['deduction_reason'],
            'notes' => $data['notes'],
        ]);
    }

    private function recordRefundTransaction(Lease $lease, int $landlordId, RefundDepositRequest $request): void
    {
        $refundAmount = $request->refund_amount;
        $deductions = $request->deductions ?? 0;
        $balanceAfter = $lease->deposit_amount - $refundAmount - $deductions;
        $type = $deductions > 0 ? DepositTransaction::TYPE_PARTIAL_REFUND : DepositTransaction::TYPE_FULL_REFUND;

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlordId,
            'processed_by' => auth()->id(),
            'type' => $type,
            'amount' => $refundAmount,
            'balance_after' => $balanceAfter,
            'reason' => 'Deposit refund',
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'notes' => $request->notes,
        ]);
    }

    private function notifyTenantOfRefund(Lease $lease, string $status): void
    {
        if ($lease->tenant?->email) {
            Mail::to($lease->tenant->email)->queue(new DepositRefundNotification($lease, $status));
        }
    }

    public function forfeit(ForfeitDepositRequest $request, Lease $lease): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($lease->deposit_status !== 'held') {
            return back()->withErrors(['error' => 'This deposit has already been processed.']);
        }

        $previousDepositStatus = $lease->deposit_status;

        $lease->update([
            'deposit_status' => 'forfeited',
            'deposit_deductions' => $lease->deposit_amount,
            'deposit_deduction_reason' => $request->reason,
            'deposit_processed_at' => now(),
            'deposit_processed_by' => auth()->id(),
        ]);

        // AUDIT-6: emit status_changed for the forfeit so the actor + reason
        // are recoverable from AuditLog.
        $lease->logStatusChange(
            "deposit:{$previousDepositStatus}",
            'deposit:forfeited',
            $request->reason ?? 'Deposit forfeited',
        );

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlordId,
            'processed_by' => auth()->id(),
            'type' => DepositTransaction::TYPE_FORFEIT,
            'amount' => $lease->deposit_amount,
            'balance_after' => 0,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);

        if ($lease->tenant?->email) {
            Mail::to($lease->tenant->email)->queue(new DepositRefundNotification($lease, 'forfeited'));
        }

        return back()->with('success', 'Deposit forfeited successfully.');
    }

    public function transactions(Request $request, Lease $lease): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $transactions = $lease->depositTransactions()
            ->with('processedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->getTypeLabel(),
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'reason' => $t->reason,
                'payment_method' => $t->payment_method,
                'reference' => $t->reference,
                'processed_by' => $t->processedBy?->name,
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ]);

        return $this->jsonWithCache([
            'transactions' => $transactions,
            'deposit_amount' => $lease->deposit_amount,
            'deposit_status' => $lease->deposit_status,
        ], 30, 120);
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_merge(
            ['landlord_id' => $this->getLandlordId()],
            $request->only(['status', 'building_id'])
        );

        return $this->exportService->exportDeposits($filters, $request->query('format', 'xlsx'));
    }
}
