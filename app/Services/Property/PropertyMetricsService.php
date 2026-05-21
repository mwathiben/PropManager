<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Phase-78 PROPERTY-METRICS: per-property portfolio aggregation — building/unit
 * counts, occupancy, monthly rent roll (active leases), and outstanding arrears.
 * Strictly landlord-scoped (filters buildings.landlord_id); all sums batched in
 * grouped queries keyed by property_id (no per-property loops, no N+1).
 */
class PropertyMetricsService
{
    /**
     * @return array{property_id:int, name:string, building_count:int, unit_count:int, occupied_count:int, vacant_count:int, occupancy_pct:float, monthly_rent_roll:float, outstanding_arrears:float}
     */
    public function forProperty(Property $property): array
    {
        $rows = $this->compute((int) $property->landlord_id, (int) $property->id);

        return $rows[0] ?? $this->zeroRow((int) $property->id, (string) $property->name);
    }

    /**
     * @return list<array{property_id:int, name:string, building_count:int, unit_count:int, occupied_count:int, vacant_count:int, occupancy_pct:float, monthly_rent_roll:float, outstanding_arrears:float}>
     */
    public function forLandlord(int $landlordId): array
    {
        return $this->compute($landlordId, null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compute(int $landlordId, ?int $propertyId): array
    {
        $buildings = DB::table('buildings')
            ->where('landlord_id', $landlordId)
            ->whereNull('deleted_at')
            ->when($propertyId !== null, fn ($q) => $q->where('property_id', $propertyId))
            ->groupBy('property_id')
            ->selectRaw('property_id, COUNT(*) as cnt')
            ->get()->keyBy('property_id');

        $units = DB::table('units')
            ->join('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('buildings.landlord_id', $landlordId)
            ->whereNull('units.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->when($propertyId !== null, fn ($q) => $q->where('buildings.property_id', $propertyId))
            ->groupBy('buildings.property_id')
            ->selectRaw('buildings.property_id as pid, COUNT(units.id) as total, SUM(CASE WHEN units.status = ? THEN 1 ELSE 0 END) as occupied', ['occupied'])
            ->get()->keyBy('pid');

        $rent = DB::table('leases')
            ->join('units', 'units.id', '=', 'leases.unit_id')
            ->join('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('buildings.landlord_id', $landlordId)
            ->where('leases.is_active', true)
            ->whereNull('leases.deleted_at')
            ->whereNull('units.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->when($propertyId !== null, fn ($q) => $q->where('buildings.property_id', $propertyId))
            ->groupBy('buildings.property_id')
            ->selectRaw('buildings.property_id as pid, SUM(leases.rent_amount) as roll')
            ->get()->keyBy('pid');

        $arrears = DB::table('invoices')
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->join('units', 'units.id', '=', 'leases.unit_id')
            ->join('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('buildings.landlord_id', $landlordId)
            ->whereNull('invoices.voided_at')
            ->whereColumn('invoices.amount_paid', '<', 'invoices.total_due')
            ->whereNull('buildings.deleted_at')
            ->when($propertyId !== null, fn ($q) => $q->where('buildings.property_id', $propertyId))
            ->groupBy('buildings.property_id')
            ->selectRaw('buildings.property_id as pid, SUM(invoices.total_due - invoices.amount_paid) as arrears')
            ->get()->keyBy('pid');

        return Property::query()
            ->where('landlord_id', $landlordId)
            ->when($propertyId !== null, fn ($q) => $q->where('id', $propertyId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Property $p) use ($buildings, $units, $rent, $arrears) {
                $total = (int) ($units[$p->id]->total ?? 0);
                $occupied = (int) ($units[$p->id]->occupied ?? 0);

                return [
                    'property_id' => $p->id,
                    'name' => $p->name,
                    'building_count' => (int) ($buildings[$p->id]->cnt ?? 0),
                    'unit_count' => $total,
                    'occupied_count' => $occupied,
                    'vacant_count' => max(0, $total - $occupied),
                    'occupancy_pct' => $total > 0 ? round($occupied / $total * 100, 1) : 0.0,
                    'monthly_rent_roll' => round((float) ($rent[$p->id]->roll ?? 0), 2),
                    'outstanding_arrears' => round((float) ($arrears[$p->id]->arrears ?? 0), 2),
                ];
            })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function zeroRow(int $propertyId, string $name): array
    {
        return [
            'property_id' => $propertyId,
            'name' => $name,
            'building_count' => 0,
            'unit_count' => 0,
            'occupied_count' => 0,
            'vacant_count' => 0,
            'occupancy_pct' => 0.0,
            'monthly_rent_roll' => 0.0,
            'outstanding_arrears' => 0.0,
        ];
    }
}
