<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Phase-100 REPORTS-DEPTH-3: the rent roll — a point-in-time snapshot of every unit's
 * tenancy and its financial position (tenant, rent, deposit held, current outstanding,
 * lease window, status). The canonical artifact a landlord/PM reviews and hands to an
 * owner; distinct from the occupancy report (which is a vacancy/performance view).
 */
class RentRollService
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, generated_at: string}
     */
    public function forLandlord(int $landlordId, ?int $buildingId = null, ?int $propertyId = null): array
    {
        $units = Unit::query()
            ->whereHas('building', function ($q) use ($landlordId, $buildingId, $propertyId) {
                $q->where('landlord_id', $landlordId);
                if ($buildingId !== null) {
                    $q->where('id', $buildingId);
                }
                if ($propertyId !== null) {
                    $q->where('property_id', $propertyId);
                }
            })
            ->with([
                'building:id,name,property_id',
                'building.property:id,name',
                'activeLease.tenant:id,name',
            ])
            ->orderBy('building_id')
            ->orderBy('unit_number')
            ->get();

        // Batch the per-lease outstanding so the roll is one extra query, not N.
        $leaseIds = $units->pluck('activeLease.id')->filter()->all();
        $outstandingByLease = $this->outstandingByLease($leaseIds);

        $today = now();
        $rows = $units->map(function (Unit $unit) use ($outstandingByLease, $today): array {
            $lease = $unit->activeLease;
            $outstanding = $lease ? (float) ($outstandingByLease[$lease->id] ?? 0) : 0.0;

            return [
                'property' => $unit->building?->property?->name,
                'building' => $unit->building?->name,
                'unit' => $unit->unit_number,
                'status' => $this->status($lease, $today),
                'tenant' => $lease?->tenant?->name,
                'rent' => (float) ($lease?->rent_amount ?? 0),
                'deposit_held' => (float) ($lease?->deposit_amount ?? 0),
                'wallet_credit' => (float) ($lease?->wallet_balance ?? 0),
                'outstanding' => round($outstanding, 2),
                'lease_start' => $lease?->start_date?->format('Y-m-d'),
                'lease_end' => $lease?->end_date?->format('Y-m-d'),
            ];
        });

        return [
            'title' => 'Rent Roll',
            'rows' => $rows->values()->all(),
            'totals' => $this->totals($rows),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<int, int>  $leaseIds
     * @return Collection<int, float>
     */
    private function outstandingByLease(array $leaseIds): Collection
    {
        if (empty($leaseIds)) {
            return collect();
        }

        return Invoice::withoutGlobalScope('landlord')
            ->whereIn('lease_id', $leaseIds)
            ->whereNull('voided_at')
            ->whereRaw('amount_paid < total_due')
            ->selectRaw('lease_id, ROUND(COALESCE(SUM(GREATEST(total_due - amount_paid, 0)), 0), 2) as bal')
            ->groupBy('lease_id')
            ->pluck('bal', 'lease_id');
    }

    private function status($lease, \Carbon\CarbonInterface $today): string
    {
        if (! $lease) {
            return 'vacant';
        }

        // A lease whose end is within 60 days (or already past) is "expiring" — the
        // signal a landlord/owner acts on to renew or re-let.
        if ($lease->end_date !== null && $lease->end_date->diffInDays($today, false) >= -60) {
            return 'expiring';
        }

        return 'occupied';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows): array
    {
        $occupied = $rows->whereIn('status', ['occupied', 'expiring']);

        return [
            'units' => $rows->count(),
            'occupied' => $occupied->count(),
            'vacant' => $rows->where('status', 'vacant')->count(),
            'expiring' => $rows->where('status', 'expiring')->count(),
            'monthly_rent' => round((float) $occupied->sum('rent'), 2),
            'deposits_held' => round((float) $rows->sum('deposit_held'), 2),
            'outstanding' => round((float) $rows->sum('outstanding'), 2),
            'occupancy_rate' => $rows->count() > 0
                ? round($occupied->count() / $rows->count() * 100, 1)
                : 0.0,
        ];
    }
}
