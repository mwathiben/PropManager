<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use Illuminate\Support\Facades\DB;

class LateFeeService
{
    public function getPolicyForInvoice(Invoice $invoice): ?LateFeePolicy
    {
        $lease = $invoice->lease;
        if (! $lease) {
            return null;
        }

        $building = $lease->unit->building;
        $property = $building->property;
        $landlordId = $invoice->landlord_id;

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

    public function isEligibleForLateFeeToday(Invoice $invoice, LateFeePolicy $policy): bool
    {
        if (! $this->invoiceCanReceiveLateFee($invoice)) {
            return false;
        }

        $daysOverdue = $invoice->due_date->diffInDays(now());

        if ($daysOverdue <= $policy->grace_period_days) {
            return false;
        }

        if ($policy->max_fee_cap !== null) {
            $currentTotal = (float) $invoice->late_fees_total;
            if ($currentTotal >= $policy->max_fee_cap) {
                return false;
            }
        }

        if (! $policy->is_compounding) {
            return $invoice->lateFees()->count() === 0;
        }

        return $this->shouldApplyCompoundingFee($invoice, $policy);
    }

    protected function invoiceCanReceiveLateFee(Invoice $invoice): bool
    {
        if (! in_array($invoice->status, ['overdue', 'partial', 'sent'])) {
            return false;
        }

        if (! $invoice->due_date || ! $invoice->due_date->isPast()) {
            return false;
        }

        $outstanding = (float) $invoice->total_due - (float) $invoice->amount_paid;

        return $outstanding > 0;
    }

    protected function shouldApplyCompoundingFee(Invoice $invoice, LateFeePolicy $policy): bool
    {
        $lastFee = $invoice->lateFees()->latest('applied_date')->first();

        if (! $lastFee) {
            return true;
        }

        $daysSinceLastFee = $lastFee->applied_date->diffInDays(now());

        return match ($policy->compounding_frequency) {
            'daily' => $daysSinceLastFee >= 1,
            'weekly' => $daysSinceLastFee >= 7,
            'monthly' => $daysSinceLastFee >= 30,
            default => false,
        };
    }

    public function applyLateFee(Invoice $invoice): ?LateFee
    {
        $policy = $this->getPolicyForInvoice($invoice);

        if (! $policy) {
            return null;
        }

        if (! $this->isEligibleForLateFeeToday($invoice, $policy)) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $policy) {
            $existingFees = (float) $invoice->late_fees_total;
            $baseAmount = (float) $invoice->rent_due + (float) $invoice->water_due + (float) $invoice->arrears;

            $feeAmount = $policy->calculateFee($baseAmount, $existingFees);

            if ($feeAmount <= 0) {
                return null;
            }

            $lateFee = LateFee::create([
                'invoice_id' => $invoice->id,
                'late_fee_policy_id' => $policy->id,
                'landlord_id' => $invoice->landlord_id,
                'fee_amount' => $feeAmount,
                'cumulative_total' => $existingFees + $feeAmount,
                'applied_date' => now()->toDateString(),
                'days_overdue' => $invoice->due_date->diffInDays(now()),
            ]);

            $invoice->recalculateLateFees();

            return $lateFee;
        });
    }

    public function processAllOverdueInvoices(): array
    {
        $results = [
            'processed' => 0,
            'fees_applied' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $invoices = Invoice::with(['lease.unit.building.property'])
            ->whereIn('status', ['overdue', 'partial', 'sent'])
            ->where('due_date', '<', now())
            ->whereColumn('amount_paid', '<', 'total_due')
            ->get();

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

        return $results;
    }

    public function waiveLateFee(LateFee $lateFee, int $userId, string $reason): bool
    {
        return $lateFee->waive($userId, $reason);
    }

    public function waiveAllFeesForInvoice(Invoice $invoice, int $userId, string $reason): int
    {
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
