<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentPlanModification;
use App\Services\Tenant\PaymentPlanModificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-45 PAY-PLAN-MOD-2: landlord-side approval workflow for a
 * tenant's PaymentPlanModification request. Approve writes the
 * proposed installments into the plan; reject reverts to the original
 * schedule (which was never deleted).
 */
class PaymentPlanModificationReviewController extends Controller
{
    public function __construct(private readonly PaymentPlanModificationService $service)
    {
    }

    public function approve(Request $request, PaymentPlanModification $modification): RedirectResponse
    {
        $this->guard($request, $modification);

        $data = $request->validate([
            'landlord_response' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->approve($modification, $request->user(), $data['landlord_response'] ?? null);

        return Redirect::back()->with('success', __('workflow.payment_plan_mod.approved'));
    }

    public function reject(Request $request, PaymentPlanModification $modification): RedirectResponse
    {
        $this->guard($request, $modification);

        $data = $request->validate([
            'landlord_response' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->reject($modification, $request->user(), $data['landlord_response'] ?? null);

        return Redirect::back()->with('success', __('workflow.payment_plan_mod.rejected'));
    }

    private function guard(Request $request, PaymentPlanModification $modification): void
    {
        $user = $request->user();
        $plan = $modification->paymentPlan;

        $isOwner = $user->isSuperAdmin()
            || $plan->landlord_id === $user->id
            || $plan->landlord_id === $user->landlord_id;

        abort_unless($isOwner, 403, 'You may not review this modification.');
        abort_unless(
            $modification->status === PaymentPlanModification::STATUS_PENDING,
            422,
            'This modification is no longer pending.',
        );
    }
}
