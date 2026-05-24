<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use Carbon\CarbonInterface;

/**
 * Phase-100 REPORTS-DEPTH-3: an owner statement for a single property and period — the
 * document a property manager hands to the owner they manage on behalf of. Gross
 * collected, an expense breakdown by category, and the net due to the owner. A
 * lightweight first step toward the PM-company model (no new role/payouts yet).
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

        // Exclude voided payments — reversed money is not collected (matches the rest
        // of the app's revenue queries). Refunds are NOT netted here: like the portfolio
        // revenue report, this is a gross cash-in basis; refunds are tracked separately.
        $collected = (float) Payment::withArchived()
            ->where('landlord_id', $landlordId)
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$start, $end])
            ->whereHas('lease.unit.building', fn ($q) => $q->where('property_id', $propertyId))
            ->sum('amount');

        // Building wins over property_id (single attribution; no cross-property double-count).
        $expenseRows = Expense::query()
            ->where('landlord_id', $landlordId)
            ->whereBetween('expense_date', [$start, $end])
            ->where(fn ($q) => $q
                ->where(fn ($d) => $d->whereNull('building_id')->where('property_id', $propertyId))
                ->orWhereHas('building', fn ($b) => $b->where('property_id', $propertyId)))
            ->with('category:id,name')
            ->get(['id', 'category_id', 'amount']);

        $expenses = $expenseRows
            ->groupBy(fn (Expense $e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $name) => [
                'category' => $name,
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $totalExpenses = round((float) $expenseRows->sum('amount'), 2);
        $collected = round($collected, 2);

        return [
            'property' => ['id' => $property->id, 'name' => $property->name],
            'period' => ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
            'collected' => $collected,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net' => round($collected - $totalExpenses, 2),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }
}
