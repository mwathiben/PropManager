<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Events\DepositRefundApproved;
use App\Events\DepositRefundPaid;
use App\Events\DepositRefundRejected;
use App\Http\Controllers\Controller;
use App\Models\DepositRefundRequest;
use App\Services\Mpesa\DepositRefundPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-29 WF-PAY-APPROVE-2: closes the Phase-28 deferred landlord
 * approval UI for tenant-requested deposit refunds.
 *
 * Status machine: submitted → under_review → (approved | rejected) →
 * paid. approve() captures final_amount_cents which may differ from
 * requested when move-out deductions apply. markPaid() requires
 * status=approved and captures payment_reference + paid_at.
 */
class DepositRefundApprovalController extends Controller
{
    public function approve(Request $request, DepositRefundRequest $refund): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $refund);
        abort_unless(
            in_array($refund->status, [
                DepositRefundRequest::STATUS_SUBMITTED,
                DepositRefundRequest::STATUS_UNDER_REVIEW,
            ], true),
            422,
            'Only submitted or under_review refunds can be approved.',
        );

        $data = $request->validate([
            'final_amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        $refund->update([
            'status' => DepositRefundRequest::STATUS_APPROVED,
            'final_amount_cents' => $data['final_amount_cents'],
            'reviewed_at' => now(),
        ]);

        DepositRefundApproved::dispatch($refund);

        return Redirect::back()->with('success', __('workflow.deposit_refund.approved'));
    }

    public function reject(Request $request, DepositRefundRequest $refund): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $refund);
        abort_unless(
            in_array($refund->status, [
                DepositRefundRequest::STATUS_SUBMITTED,
                DepositRefundRequest::STATUS_UNDER_REVIEW,
            ], true),
            422,
            'Only submitted or under_review refunds can be rejected.',
        );

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $refund->update([
            'status' => DepositRefundRequest::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'reviewed_at' => now(),
        ]);

        DepositRefundRejected::dispatch($refund);

        return Redirect::back()->with('success', __('workflow.deposit_refund.rejected'));
    }

    public function markPaid(Request $request, DepositRefundRequest $refund): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $refund);
        abort_unless(
            $refund->status === DepositRefundRequest::STATUS_APPROVED,
            422,
            'Only approved refunds can be marked paid.',
        );

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:120'],
        ]);

        $refund->update([
            'status' => DepositRefundRequest::STATUS_PAID,
            'payment_reference' => $data['payment_reference'],
            'paid_at' => now(),
        ]);

        DepositRefundPaid::dispatch($refund);

        return Redirect::back()->with('success', __('workflow.deposit_refund.paid'));
    }

    /**
     * Phase-30 INT-MPESA-DEEP-1: pay the approved refund via M-Pesa
     * B2C. Idempotent — repeated calls return the same MpesaB2cRequest
     * row; the DepositRefundRequest does NOT flip to PAID here. It is
     * flipped to PAID when the B2C ResultURL callback or the
     * mpesa:reconcile-status poll confirms 'succeeded'.
     */
    public function payViaMpesa(
        Request $request,
        DepositRefundRequest $refund,
        DepositRefundPayoutService $payoutService,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('manage', $refund);
        abort_unless(
            $refund->status === DepositRefundRequest::STATUS_APPROVED,
            422,
            'Only approved refunds can be paid via M-Pesa B2C.',
        );

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?254[0-9]{9}$/'],
        ]);

        try {
            $b2c = $payoutService->payout($refund, $data['phone']);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return Redirect::back()->with('success', __('workflow.deposit_refund.b2c_'.$b2c->status));
    }
}
