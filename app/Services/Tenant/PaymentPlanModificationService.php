<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanModification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase-45 PAY-PLAN-MOD-1/2/3: validates + persists a tenant's request
 * to modify the installment schedule of an approved PaymentPlan.
 *
 * Safeguards:
 *  - The plan must be in STATUS_APPROVED (cannot modify a still-pending request
 *    or one that already has a pending modification).
 *  - The sum of proposed installment amounts MUST equal the sum of currently
 *    UNPAID installments — modification cannot reduce the outstanding balance.
 *  - Paid installments are immutable: a modification cannot touch them.
 *  - At least 2 proposed installments (a single-installment "modification" is
 *    just a deferred lump-sum payment, which is a different flow).
 */
class PaymentPlanModificationService
{
    /**
     * @param  list<array{due_date: string, amount_cents: int}>  $proposed
     */
    public function propose(PaymentPlan $plan, array $proposed, User $tenant): PaymentPlanModification
    {
        $this->validatePlanState($plan);
        $unpaid = $plan->installments()->where('status', PaymentPlanInstallment::STATUS_PENDING)->get();
        $this->validateProposed($proposed, $unpaid);

        return DB::transaction(function () use ($plan, $proposed, $unpaid, $tenant): PaymentPlanModification {
            $modification = PaymentPlanModification::create([
                'payment_plan_id' => $plan->id,
                'requested_by_user_id' => $tenant->id,
                'original_installments' => $unpaid->map(fn (PaymentPlanInstallment $i): array => [
                    'id' => $i->id,
                    'due_date' => $i->due_date->toDateString(),
                    'amount_cents' => $i->amount_cents,
                ])->all(),
                'proposed_installments' => $proposed,
                'status' => PaymentPlanModification::STATUS_PENDING,
            ]);

            $plan->update(['status' => PaymentPlan::STATUS_MODIFIED_PENDING]);

            return $modification;
        });
    }

    public function approve(PaymentPlanModification $modification, User $landlord, ?string $response = null): void
    {
        if ($modification->status !== PaymentPlanModification::STATUS_PENDING) {
            throw ValidationException::withMessages(['modification' => 'Modification is not pending.']);
        }

        DB::transaction(function () use ($modification, $landlord, $response): void {
            $plan = $modification->paymentPlan;

            // Delete the unpaid installments + insert the proposed schedule.
            $plan->installments()->where('status', PaymentPlanInstallment::STATUS_PENDING)->delete();
            foreach ($modification->proposed_installments as $row) {
                PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'due_date' => $row['due_date'],
                    'amount_cents' => $row['amount_cents'],
                    'paid_amount_cents' => 0,
                    'status' => PaymentPlanInstallment::STATUS_PENDING,
                ]);
            }

            $modification->update([
                'status' => PaymentPlanModification::STATUS_APPROVED,
                'landlord_response' => $response,
                'decided_at' => now(),
                'decided_by_user_id' => $landlord->id,
            ]);

            $plan->update(['status' => PaymentPlan::STATUS_APPROVED]);
        });
    }

    public function reject(PaymentPlanModification $modification, User $landlord, ?string $response = null): void
    {
        if ($modification->status !== PaymentPlanModification::STATUS_PENDING) {
            throw ValidationException::withMessages(['modification' => 'Modification is not pending.']);
        }

        DB::transaction(function () use ($modification, $landlord, $response): void {
            $modification->update([
                'status' => PaymentPlanModification::STATUS_REJECTED,
                'landlord_response' => $response,
                'decided_at' => now(),
                'decided_by_user_id' => $landlord->id,
            ]);

            // Revert the plan back to approved — the original installments
            // (still in the DB, untouched) remain in effect.
            $modification->paymentPlan->update(['status' => PaymentPlan::STATUS_APPROVED]);
        });
    }

    private function validatePlanState(PaymentPlan $plan): void
    {
        if ($plan->status !== PaymentPlan::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'plan' => 'Only approved payment plans can be modified.',
            ]);
        }
    }

    /**
     * @param  list<array{due_date: string, amount_cents: int}>  $proposed
     * @param  \Illuminate\Database\Eloquent\Collection<int, PaymentPlanInstallment>  $unpaid
     */
    private function validateProposed(array $proposed, $unpaid): void
    {
        if (count($proposed) < 2) {
            throw ValidationException::withMessages([
                'proposed_installments' => 'At least 2 installments required for a modification.',
            ]);
        }

        $proposedTotal = array_sum(array_map(static fn (array $row): int => (int) $row['amount_cents'], $proposed));
        $unpaidTotal = (int) $unpaid->sum('amount_cents');

        if ($proposedTotal !== $unpaidTotal) {
            throw ValidationException::withMessages([
                'proposed_installments' => sprintf(
                    'Proposed total %d must equal unpaid balance %d.',
                    $proposedTotal,
                    $unpaidTotal,
                ),
            ]);
        }
    }
}
