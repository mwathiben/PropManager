<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Late Fee Service - Calculates and applies late fees based on policy hierarchy.
 *
 * Policy Hierarchy (most specific wins):
 * 1. Building-level policy - for buildings needing strict/lenient rules
 * 2. Property-level policy - shared across buildings in a property
 * 3. Landlord default policy - applies to all properties without overrides
 *
 * WHY this hierarchy: Different buildings may warrant different fee policies
 * (e.g., commercial properties with stricter enforcement vs residential with
 * more lenient grace periods).
 *
 * Late Fee Calculation Algorithm:
 * - Grace period protects tenants from immediate penalties (industry standard)
 * - Fee cap prevents runaway charges that could exceed the original debt
 * - Compounding frequency controls how often fees accumulate (daily/weekly/monthly)
 * - Non-compounding fees apply once only (simpler for small landlords)
 *
 * WHY fee cap exists: Tenant protection - without caps, compound interest on
 * late fees could theoretically exceed the original invoice amount, creating
 * an unenforceable or legally questionable debt.
 */
class LateFeeService
{
    private ?\Illuminate\Support\Collection $policyCache = null;

    /**
     * Find the applicable late fee policy using 3-tier hierarchy.
     * Building > Property > Landlord default (most specific wins).
     */
    public function getPolicyForInvoice(Invoice $invoice): ?LateFeePolicy
    {
        $lease = $invoice->lease;
        if (! $lease) {
            return null;
        }

        $building = $lease->unit->building;
        $property = $building->property;
        $landlordId = $invoice->landlord_id;

        if ($this->policyCache) {
            $landlordPolicies = $this->policyCache->where('landlord_id', $landlordId);

            return $landlordPolicies->firstWhere('building_id', $building->id)
                ?? $landlordPolicies->where('property_id', $property->id)->whereNull('building_id')->first()
                ?? $landlordPolicies->whereNull('property_id')->whereNull('building_id')->first();
        }

        $policy = LateFeePolicy::active()
            ->where('landlord_id', $landlordId)
            ->where('building_id', $building->id)
            ->first();

        if ($policy) {
            return $policy;
        }

        $policy = LateFeePolicy::active()
            ->where('landlord_id', $landlordId)
            ->where('property_id', $property->id)
            ->whereNull('building_id')
            ->first();

        if ($policy) {
            return $policy;
        }

        return LateFeePolicy::active()
            ->where('landlord_id', $landlordId)
            ->whereNull('property_id')
            ->whereNull('building_id')
            ->first();
    }

    /**
     * Check if invoice is eligible for a late fee today.
     *
     * Eligibility gates (all must pass):
     * - Invoice status allows fees (Overdue, Partial, Sent)
     * - Past due date with outstanding balance
     * - Past grace period (tenant protection window)
     * - Under fee cap (prevents runaway charges)
     * - Compounding frequency satisfied (prevents fee spam)
     */
    public function isEligibleForLateFeeToday(Invoice $invoice, LateFeePolicy $policy): bool
    {
        if (! $this->invoiceCanReceiveLateFee($invoice)) {
            return false;
        }

        $daysOverdue = $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay());

        // Grace period: Industry-standard protection before penalties kick in
        if ($daysOverdue <= $policy->grace_period_days) {
            return false;
        }

        // Fee cap: Prevents cumulative fees from exceeding reasonable limits
        if ($policy->max_fee_cap !== null) {
            $currentTotal = (float) $invoice->late_fees_total;
            if ($currentTotal >= $policy->max_fee_cap) {
                return false;
            }
        }

        // Non-compounding: Single fee only (simpler for small landlords)
        if (! $policy->is_compounding) {
            return $invoice->lateFees()->count() === 0;
        }

        return $this->shouldApplyCompoundingFee($invoice, $policy);
    }

    protected function invoiceCanReceiveLateFee(Invoice $invoice): bool
    {
        if (! in_array($invoice->status, [InvoiceStatus::Overdue, InvoiceStatus::Partial, InvoiceStatus::Sent])) {
            return false;
        }

        if (! $invoice->due_date || ! $invoice->due_date->isPast()) {
            return false;
        }

        // Phase-17 MONEY-2: bcmath-backed comparison. Pre-fix float
        // subtraction produced "outstanding" values within 0.01 KES of
        // zero that were positive but visually rounded to 0.00, applying
        // a fee against a zero-balance invoice.
        return $invoice->getOutstandingMoney()->isPositive();
    }

    /**
     * Check if enough time has passed for next compounding fee.
     *
     * WHY frequency limits: Prevents fee spam by ensuring fees only
     * accumulate at the configured rate (daily/weekly/monthly).
     * Without this, fees could theoretically apply multiple times per day.
     */
    protected function shouldApplyCompoundingFee(Invoice $invoice, LateFeePolicy $policy): bool
    {
        $lastFee = $invoice->lateFees()->latest('applied_date')->first();

        if (! $lastFee) {
            return true;
        }

        $today = now()->startOfDay();
        $lastApplied = $lastFee->applied_date->startOfDay();

        // Phase-17 TIME-5: addMonthNoOverflow clamps Jan 31 + 1 month to
        // Feb 28/29 (instead of overflowing to Mar 3). Without this, a
        // monthly-compounding policy that first fires on Jan 31 drifts
        // its cadence by ~3 days every year. addMonthNoOverflow keeps
        // the day-of-month anchored where possible.
        $nextDue = match ($policy->compounding_frequency) {
            'daily' => $lastApplied->copy()->addDay(),
            'weekly' => $lastApplied->copy()->addWeek(),
            'monthly' => $lastApplied->copy()->addMonthNoOverflow(),
            default => null,
        };

        return $nextDue !== null && $today->gte($nextDue);
    }

    public function applyLateFee(Invoice $invoice): ?LateFee
    {
        $policy = $this->getPolicyForInvoice($invoice);

        if (! $policy) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $policy) {
            // CONC-6: re-fetch under lockForUpdate and re-check eligibility
            // INSIDE the transaction. The pre-fix path checked eligibility
            // outside the lock, so two parallel scheduler runs both saw
            // count=0 and both inserted a LateFee. The unique index added
            // by the Phase 4 indexes migration (late_fees_invoice_applied_unique)
            // would now reject the second insert at the DB layer; this lock
            // makes the failure path deterministic instead of a race-loser.
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $this->isEligibleForLateFeeToday($invoice, $policy)) {
                return null;
            }

            // Phase-17 MONEY-2: bcmath-backed accumulation. Compounding
            // monthly over 12 months in float drifts by ~0.06 KES against
            // a known closed-form; Money holds the exact decimal sum.
            $existingFees = Money::fromString((string) $invoice->late_fees_total);
            $baseAmount = Money::fromString((string) $invoice->rent_due)
                ->add(Money::fromString((string) $invoice->water_due))
                ->add(Money::fromString((string) $invoice->arrears));

            $feeAmount = $policy->calculateFeeMoney($baseAmount, $existingFees);

            if (! $feeAmount->isPositive()) {
                return null;
            }

            $cumulativeTotal = $existingFees->add($feeAmount);

            $lateFee = LateFee::create([
                'invoice_id' => $invoice->id,
                'late_fee_policy_id' => $policy->id,
                'landlord_id' => $invoice->landlord_id,
                'fee_amount' => $feeAmount->toDecimalString(),
                'cumulative_total' => $cumulativeTotal->toDecimalString(),
                'applied_date' => now()->toDateString(),
                'days_overdue' => $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay()),
            ]);

            $invoice->recalculateLateFees();

            return $lateFee;
        });
    }

    /**
     * Phase-81 LATE-FEE-DEPTH-1: the daily cron passes nothing (all landlords);
     * the on-demand landlord apply passes its id to scope the run.
     */
    public function processAllOverdueInvoices(?int $landlordId = null): array
    {
        $results = [
            'processed' => 0,
            'fees_applied' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $invoices = Invoice::with(['lease.unit.building.property', 'lateFees'])
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial, InvoiceStatus::Sent])
            ->where('due_date', '<', now())
            ->whereColumn('amount_paid', '<', 'total_due')
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId))
            ->get();

        $landlordIds = $invoices->pluck('landlord_id')->unique();
        $activePolicies = LateFeePolicy::active()
            ->whereIn('landlord_id', $landlordIds)
            ->get();
        $this->policyCache = $activePolicies;

        foreach ($invoices as $invoice) {
            $results['processed']++;

            try {
                $lateFee = $this->applyLateFee($invoice);

                if ($lateFee) {
                    $results['fees_applied']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->policyCache = null;

        return $results;
    }

    public function waiveLateFee(LateFee $lateFee, int $userId, string $reason): bool
    {
        return $lateFee->waive($userId, $reason);
    }

    public function waiveAllFeesForInvoice(Invoice $invoice, int $userId, string $reason): int
    {
        return DB::transaction(function () use ($invoice, $userId, $reason) {
            $count = 0;

            foreach ($invoice->lateFees()->where('is_waived', false)->get() as $fee) {
                $fee->update([
                    'is_waived' => true,
                    'waived_by' => $userId,
                    'waived_at' => now(),
                    'waiver_reason' => $reason,
                ]);
                $count++;
            }

            $invoice->recalculateLateFees();

            return $count;
        });
    }

    public function previewLateFee(Invoice $invoice): ?array
    {
        $policy = $this->getPolicyForInvoice($invoice);

        if (! $policy) {
            return null;
        }

        $baseAmount = (float) $invoice->rent_due + (float) $invoice->water_due + (float) $invoice->arrears;
        $existingFees = (float) $invoice->late_fees_total;
        $projectedFee = $policy->calculateFee($baseAmount, $existingFees);
        $daysOverdue = $invoice->due_date && $invoice->due_date->isPast()
            ? $invoice->due_date->diffInDays(now())
            : 0;

        return [
            'policy' => $policy,
            'base_amount' => $baseAmount,
            'existing_fees' => $existingFees,
            'projected_fee' => $projectedFee,
            'days_overdue' => $daysOverdue,
            'grace_days_remaining' => max(0, $policy->grace_period_days - $daysOverdue),
            'eligible' => $this->isEligibleForLateFeeToday($invoice, $policy),
        ];
    }
}
