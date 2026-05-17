<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PaymentPlan;
use App\Services\Tenant\PaymentPlanModificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-45 PAY-PLAN-MOD-1: tenant proposes a new installment schedule
 * for an already-approved PaymentPlan. The plan transitions
 * STATUS_APPROVED → STATUS_MODIFIED_PENDING; landlord owns the next
 * decision via PaymentPlanModificationReviewController.
 */
class PaymentPlanModificationController extends Controller
{
    public function __construct(private readonly PaymentPlanModificationService $service)
    {
    }

    public function store(Request $request, PaymentPlan $plan): RedirectResponse
    {
        abort_unless(
            $plan->tenant_id === $request->user()->id,
            403,
            'You can only modify your own payment plans.',
        );

        $validated = $request->validate([
            'installments' => ['required', 'array', 'min:2'],
            'installments.*.due_date' => ['required', 'date', 'after:today'],
            'installments.*.amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->propose($plan, array_values($validated['installments']), $request->user());

        return Redirect::route('tenant.finances.index')
            ->with('success', __('workflow.payment_plan_mod.proposed'));
    }
}
