<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Phase-100 REPORTS-DEPTH-3: per-PROPERTY profit & loss for a period. The existing
 * finance reports aggregate revenue/expense across the whole portfolio (by month);
 * this breaks it down per property so a landlord/PM can see which properties earn and
 * which leak — and so an owner statement (100-C) has a per-property basis.
 *
 * Revenue = cash collected (payments on the property's leases) — not invoiced — so the
 * P&L reflects actual money in. Expenses = costs tagged to the property (directly via
 * property_id, or via the building that belongs to it).
 */
class PropertyPnlService
{
    /**
     * @return array{title: string, rows: array<int, array<string, mixed>>, totals: array<string, mixed>, period: array{start: string, end: string}, generated_at: string}
     */
    public function forLandlord(int $landlordId, CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): array
    {
        $properties = Property::query()
            ->where('landlord_id', $landlordId)
            ->when($propertyId !== null, fn ($q) => $q->where('id', $propertyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $properties->map(function (Property $property) use ($landlordId, $start, $end): array {
            // Voided payments are reversed money — exclude them (every other "collected"
            // query in the app filters is_voided; omitting it overstates the P&L and
            // drifts from the portfolio revenue report).
            $collected = (float) Payment::withArchived()
                ->where('landlord_id', $landlordId)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$start, $end])
                ->whereHas('lease.unit.building', fn ($q) => $q->where('property_id', $property->id))
                ->sum('amount');

            // Building wins over property_id so an expense tagged with both isn't counted
            // under two properties (and double-counted in the portfolio total).
            $expenses = (float) Expense::where('landlord_id', $landlordId)
                ->whereBetween('expense_date', [$start, $end])
                ->where(fn ($q) => $q
                    ->where(fn ($d) => $d->whereNull('building_id')->where('property_id', $property->id))
                    ->orWhereHas('building', fn ($b) => $b->where('property_id', $property->id)))
                ->sum('amount');

            return [
                'property' => $property->name,
                'collected' => round($collected, 2),
                'expenses' => round($expenses, 2),
                'net' => round($collected - $expenses, 2),
                'margin' => $collected > 0 ? round(($collected - $expenses) / $collected * 100, 1) : 0.0,
            ];
        });

        return [
            'title' => 'Per-Property Profit & Loss',
            'rows' => $rows->values()->all(),
            'totals' => $this->totals($rows),
            'period' => ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows): array
    {
        $collected = round((float) $rows->sum('collected'), 2);
        $expenses = round((float) $rows->sum('expenses'), 2);

        return [
            'properties' => $rows->count(),
            'collected' => $collected,
            'expenses' => $expenses,
            'net' => round($collected - $expenses, 2),
            'margin' => $collected > 0 ? round(($collected - $expenses) / $collected * 100, 1) : 0.0,
        ];
    }
}
