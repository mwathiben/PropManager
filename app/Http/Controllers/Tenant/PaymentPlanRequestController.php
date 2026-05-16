<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

/**
 * Phase-28 TENANT-PAY-1: tenant-initiated payment plan request.
 *
 * The tenant picks an invoice they own + number of installments + the
 * first due date. The service splits total_amount equally, absorbing
 * the cents remainder on the last installment (Phase-17 Money pattern).
 * An invoice with an active plan (requested or approved) cannot
 * receive a second request — the validator rejects the duplicate.
 *
 * Landlord approval flow is intentionally NOT included in this commit;
 * see audit_closeout deferral entry "TENANT-PAY landlord-approval UI".
 */
class PaymentPlanRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user();

        $data = $request->validate([
            'invoice_id' => [
                'required',
                Rule::exists('invoices', 'id')
                    ->where(fn ($q) => $q->where('landlord_id', $tenant->landlord_id)),
            ],
            'installment_count' => ['required', 'integer', 'min:2', 'max:12'],
            'first_due_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::query()
            ->withoutGlobalScope('landlord')
            ->where('id', $data['invoice_id'])
            ->where('landlord_id', $tenant->landlord_id)
            ->firstOrFail();

        // The invoice must belong to a lease owned by this tenant.
        abort_unless(
            $invoice->lease && $invoice->lease->tenant_id === $tenant->id,
            403,
            'Cannot request a plan for an invoice you do not own.',
        );

        $hasActivePlan = PaymentPlan::where('invoice_id', $invoice->id)
            ->whereIn('status', [PaymentPlan::STATUS_REQUESTED, PaymentPlan::STATUS_APPROVED])
            ->exists();
        abort_if($hasActivePlan, 422, 'An active payment plan already exists for this invoice.');

        $plan = DB::transaction(function () use ($tenant, $invoice, $data) {
            $totalCents = (int) round(($invoice->total_due - $invoice->amount_paid) * 100);
            abort_if($totalCents <= 0, 422, 'Invoice has no remaining balance.');

            $plan = PaymentPlan::create([
                'landlord_id' => $tenant->landlord_id,
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'total_amount_cents' => $totalCents,
                'status' => PaymentPlan::STATUS_REQUESTED,
                'reason' => $data['reason'] ?? null,
            ]);

            $count = (int) $data['installment_count'];
            $baseCents = intdiv($totalCents, $count);
            $remainder = $totalCents - ($baseCents * $count);
            $firstDue = CarbonImmutable::parse($data['first_due_date']);

            for ($i = 0; $i < $count; $i++) {
                $amount = $baseCents + ($i === $count - 1 ? $remainder : 0);
                PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'due_date' => $firstDue->copy()->addMonths($i)->toDateString(),
                    'amount_cents' => $amount,
                    'paid_amount_cents' => 0,
                    'status' => PaymentPlanInstallment::STATUS_PENDING,
                ]);
            }

            return $plan;
        });

        return Redirect::route('tenant.finances.index')
            ->with('success', __('tenant.payment_plan.submitted', ['count' => count($plan->installments)]));
    }
}
