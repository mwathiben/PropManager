<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyOwner;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Phase-100/101 owner statements — the document a property manager hands to the owner
 * they manage on behalf of. Gross collected (excluding voided), an expense breakdown by
 * category, and net due to the owner. forProperty() = one property (Phase-100);
 * forOwner() = all properties an owner holds (Phase-101). A gross cash-in basis;
 * refunds are tracked separately, like the portfolio revenue report.
 */
class OwnerStatementService
{
    /**
     * @return array{property: array{id:int, name:string}, period: array{start:string, end:string}, collected: float, expenses: array<int, array{category:string, amount:float}>, total_expenses: float, net: float, generated_at: string}|null
     */
    public function forProperty(int $landlordId, int $propertyId, CarbonInterface $start, CarbonInterface $end): ?array
    {
        $property = Property::where('landlord_id', $landlordId)->find($propertyId);
        if (! $property) {
            return null; // not this landlord's property → caller 404s
        }

        $agg = $this->aggregate($landlordId, [$property->id], $start, $end);

        return [
            'property' => ['id' => $property->id, 'name' => $property->name],
            'period' => ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
            'collected' => $agg['collected'],
            'expenses' => $agg['expenses'],
            'total_expenses' => $agg['total_expenses'],
            'net' => round($agg['collected'] - $agg['total_expenses'], 2),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * Phase-101: a consolidated statement across every property an owner holds.
     *
     * @return array{owner: array{id:int, name:string, email:?string}, period: array{start:string, end:string}, collected: float, expenses: array<int, array{category:string, amount:float}>, total_expenses: float, management_fee: float, fee_type: string, fee_value: float, net: float, properties: array<int, array{name:string, collected:float, expenses:float, net:float}>, generated_at: string}|null
     */
    public function forOwner(int $landlordId, int $ownerId, CarbonInterface $start, CarbonInterface $end): ?array
    {
        $owner = PropertyOwner::where('landlord_id', $landlordId)->find($ownerId);
        if (! $owner) {
            return null; // not this landlord's owner → caller 404s
        }

        $properties = Property::where('landlord_id', $landlordId)
            ->where('property_owner_id', $ownerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyIds = $properties->pluck('id')->all();
        $agg = $this->aggregate($landlordId, $propertyIds, $start, $end);

        // Per-property breakdown so the owner sees each property's contribution. These rows
        // stay pre-fee (collected - expenses) — the management fee is a portfolio-level
        // deduction on total collected, shown as its own line, not split per property.
        $breakdown = $properties->map(function (Property $p) use ($landlordId, $start, $end): array {
            $one = $this->aggregate($landlordId, [$p->id], $start, $end);

            return [
                'name' => $p->name,
                'collected' => $one['collected'],
                'expenses' => $one['total_expenses'],
                'net' => round($one['collected'] - $one['total_expenses'], 2),
            ];
        })->all();

        // Phase-103: the PM's management fee (the owner-facing deduction only — forProperty,
        // which feeds the landlord's own reports, stays fee-free). Default type 'none' => 0.
        $managementFee = $owner->managementFeeOn($agg['collected']);

        return [
            'owner' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email],
            'period' => ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
            'collected' => $agg['collected'],
            'expenses' => $agg['expenses'],
            'total_expenses' => $agg['total_expenses'],
            'management_fee' => $managementFee,
            'fee_type' => (string) $owner->management_fee_type,
            'fee_value' => (float) $owner->management_fee_value,
            'net' => round($agg['collected'] - $agg['total_expenses'] - $managementFee, 2),
            'properties' => $breakdown,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * Shared aggregation over a set of properties: collected (cash in, excluding voided)
     * + expenses grouped by category (building wins over property_id for attribution).
     *
     * @param  array<int, int>  $propertyIds
     * @return array{collected: float, expenses: array<int, array{category:string, amount:float}>, total_expenses: float}
     */
    private function aggregate(int $landlordId, array $propertyIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if (empty($propertyIds)) {
            return ['collected' => 0.0, 'expenses' => [], 'total_expenses' => 0.0];
        }

        $collected = (float) Payment::withArchived()
            ->where('landlord_id', $landlordId)
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$start, $end])
            ->whereHas('lease.unit.building', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->sum('amount');

        $expenseRows = Expense::query()
            ->where('landlord_id', $landlordId)
            ->whereBetween('expense_date', [$start, $end])
            ->where(fn ($q) => $q
                ->where(fn ($d) => $d->whereNull('building_id')->whereIn('property_id', $propertyIds))
                ->orWhereHas('building', fn ($b) => $b->whereIn('property_id', $propertyIds)))
            ->with('category:id,name')
            ->get(['id', 'category_id', 'amount']);

        $expenses = $expenseRows
            ->groupBy(fn (Expense $e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn (Collection $group, string $name) => [
                'category' => $name,
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'collected' => round($collected, 2),
            'expenses' => $expenses,
            'total_expenses' => round((float) $expenseRows->sum('amount'), 2),
        ];
    }
}
