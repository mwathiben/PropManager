<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Events\PaymentPlanApproved;
use App\Events\PaymentPlanRejected;
use App\Http\Controllers\Controller;
use App\Models\PaymentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-29 WF-PAY-APPROVE-1: closes the Phase-28 deferred landlord
 * approval UI for tenant-requested payment plans.
 */
class PaymentPlanApprovalController extends Controller
{
    public function approve(Request $request, PaymentPlan $plan): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $plan);
        abort_unless(
            $plan->status === PaymentPlan::STATUS_REQUESTED,
            422,
            'Only requested plans can be approved.',
        );

        $plan->update([
            'status' => PaymentPlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $request->user()->id,
        ]);

        PaymentPlanApproved::dispatch($plan);

        return Redirect::back()->with('success', __('workflow.payment_plan.approved'));
    }

    public function reject(Request $request, PaymentPlan $plan): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $plan);
        abort_unless(
            $plan->status === PaymentPlan::STATUS_REQUESTED,
            422,
            'Only requested plans can be rejected.',
        );

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $plan->update([
            'status' => PaymentPlan::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        PaymentPlanRejected::dispatch($plan);

        return Redirect::back()->with('success', __('workflow.payment_plan.rejected'));
    }
}
