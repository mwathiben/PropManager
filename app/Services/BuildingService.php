<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuildingService
{
    public function getFilteredBuildings(int $landlordId, Request $request): Collection
    {
        $hasFilters = $request->filled('search') || $request->filled('type');
        $sort = $request->get('sort', 'name_asc');

        if (! $hasFilters && $sort === 'name_asc') {
            return $this->getCachedBuildings($landlordId);
        }

        return $this->queryBuildings($landlordId, $request, $sort);
    }

    private function getCachedBuildings(int $landlordId): Collection
    {
        $cacheKey = BuildingCacheService::listKey($landlordId);

        return Cache::remember($cacheKey, BuildingCacheService::getTtl(), function () use ($landlordId) {
            return $this->fetchBuildingsWithCounts($landlordId);
        });
    }

    private function queryBuildings(int $landlordId, Request $request, string $sort): Collection
    {
        $query = Building::where('landlord_id', $landlordId)
            ->with(['property:id,name,address'])
            ->withCount('units')
            ->withCount(['units as occupied_units_count' => function ($q) {
                $q->where('status', 'occupied');
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('building_type', $request->type);
        }

        $this->applySorting($query, $sort);

        return $this->mapBuildingDetails($query->get());
    }

    private function fetchBuildingsWithCounts(int $landlordId): Collection
    {
        $buildings = Building::where('landlord_id', $landlordId)
            ->with(['property:id,name,address'])
            ->withCount('units')
            ->withCount(['units as occupied_units_count' => function ($q) {
                $q->where('status', 'occupied');
            }])
            ->orderBy('name', 'asc')
            ->get();

        return $this->mapBuildingDetails($buildings);
    }

    private function mapBuildingDetails(Collection $buildings): Collection
    {
        return $buildings->map(function ($building) {
            $building->occupancy_rate = $building->units_count > 0
                ? round(($building->occupied_units_count / $building->units_count) * 100)
                : 0;
            $building->primary_photo = $building->photos[0] ?? null;
            $building->type_label = $building->getBuildingTypeLabel();

            return $building;
        });
    }

    public function createStandaloneBuilding(array $data, int $landlordId): Building
    {
        return DB::transaction(function () use ($data, $landlordId) {
            $property = Property::create([
                'landlord_id' => $landlordId,
                'name' => $data['name'],
                'type' => $this->inferPropertyType($data['building_type']),
                'address' => $data['address'] ?? null,
            ]);

            $building = Building::create([
                'property_id' => $property->id,
                'landlord_id' => $landlordId,
                'name' => $data['name'],
                'building_type' => $data['building_type'],
                'address' => $data['address'] ?? null,
                'description' => $data['description'] ?? null,
                'total_floors' => $data['total_floors'],
                'units_per_floor' => $data['units_per_floor'],
                'amenities' => $data['amenities'] ?? ['selected' => [], 'custom' => []],
                'coordinates' => $data['coordinates'] ?? null,
            ]);

            $this->generateUnits($building, $data['total_floors'], $data['units_per_floor'], $landlordId);

            return $building;
        });
    }

    public function createBuilding(int $propertyId, array $data, int $landlordId): Building
    {
        return DB::transaction(function () use ($propertyId, $data, $landlordId) {
            $building = Building::create([
                'property_id' => $propertyId,
                'landlord_id' => $landlordId,
                'name' => $data['name'],
                'total_floors' => $data['floors'],
                'units_per_floor' => $data['units_per_floor'],
                'is_wing' => false,
                'parent_building_id' => null,
            ]);

            $this->generateUnits($building, $data['floors'], $data['units_per_floor'], $landlordId);

            return $building;
        });
    }

    public function createWing(Building $parentBuilding, array $data, int $landlordId): Building
    {
        $prefix = strtoupper($data['unit_prefix']);

        return DB::transaction(function () use ($parentBuilding, $data, $prefix, $landlordId) {
            $wing = Building::create([
                'property_id' => $parentBuilding->property_id,
                'landlord_id' => $landlordId,
                'parent_building_id' => $parentBuilding->id,
                'name' => $data['name'],
                'unit_prefix' => $prefix,
                'total_floors' => $data['floors'],
                'units_per_floor' => $data['units_per_floor'],
                'is_wing' => true,
            ]);

            $this->generateUnits($wing, $data['floors'], $data['units_per_floor'], $landlordId, $prefix);

            return $wing;
        });
    }

    public function generateUnits(
        Building $building,
        int $floors,
        int $unitsPerFloor,
        int $landlordId,
        ?string $prefix = null
    ): void {
        for ($f = 1; $f <= $floors; $f++) {
            for ($u = 1; $u <= $unitsPerFloor; $u++) {
                $unitNumber = ($f * 100) + $u;
                if ($prefix) {
                    $unitNumber = $prefix.$unitNumber;
                }

                Unit::create([
                    'landlord_id' => $landlordId,
                    'building_id' => $building->id,
                    'floor_number' => $f,
                    'unit_number' => (string) $unitNumber,
                    'status' => 'vacant',
                    'target_rent' => 0,
                ]);
            }
        }
    }

    public function getBuildingDashboardData(Building $building, Request $request): array
    {
        $property = $building->property;
        $buildings = $property->buildings;

        $period = $request->get('period', 'this_month');
        $startDate = $this->getStartDate($period, $request);
        $endDate = $this->getEndDate($period, $request);
        $floorFilter = $request->get('floor');
        $unitTypeFilter = $request->get('unit_type');
        $statusFilter = $request->get('status');

        $periodDays = $startDate->diffInDays($endDate);
        $prevStartDate = $startDate->copy()->subDays($periodDays + 1);
        $prevEndDate = $startDate->copy()->subDay();

        $units = $this->getFilteredUnits($building, $floorFilter, $unitTypeFilter, $statusFilter);
        $allUnits = $building->units()->get();

        $allUnitIds = $allUnits->pluck('id');
        $filteredUnitIds = $units->pluck('id');
        $leaseIds = Lease::whereIn('unit_id', $allUnitIds)->pluck('id');
        $filteredLeaseIds = Lease::whereIn('unit_id', $filteredUnitIds)->pluck('id');

        $actionItems = $this->getActionItems($building, $allUnits, $leaseIds, $allUnitIds);
        $financialMetrics = $this->getFinancialMetrics($filteredUnitIds, $filteredLeaseIds, $startDate, $endDate);
        $periodComparison = $this->getPeriodComparison($filteredUnitIds, $startDate, $endDate, $prevStartDate, $prevEndDate);
        $arrearsAging = $this->getArrearsAging($filteredLeaseIds);

        $stats = [
            'total_units' => $units->count(),
            'occupied_units' => $units->where('status', 'occupied')->count(),
            'vacant_units' => $units->where('status', 'vacant')->count(),
            'arrears_units' => $units->where('status', 'arrears')->count(),
            'occupancy_rate' => $units->count() > 0
                ? round(($units->where('status', 'occupied')->count() / $units->count()) * 100, 1)
                : 0,
        ];

        $recentPayments = $this->getRecentPayments($filteredUnitIds, $startDate, $endDate);
        $recentTickets = $this->getRecentTickets($building->id);
        $expiringLeases = $this->getExpiringLeases($filteredUnitIds);

        $availableFloors = $building->units()->reorder()->distinct()->pluck('floor_number')->sort()->values();
        $availableUnitTypes = $building->units()->reorder()->distinct()->pluck('unit_type')->filter()->values();

        return [
            'property' => $property,
            'buildings' => $buildings,
            'activeBuilding' => $building,
            'units' => $units,
            'actionItems' => $actionItems,
            'financialMetrics' => $financialMetrics,
            'periodComparison' => $periodComparison,
            'arrearsAging' => $arrearsAging,
            'stats' => $stats,
            'recentPayments' => $recentPayments,
            'recentTickets' => $recentTickets,
            'expiringLeases' => $expiringLeases,
            'filters' => [
                'period' => $period,
                'floor' => $floorFilter,
                'unit_type' => $unitTypeFilter,
                'status' => $statusFilter,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'availableFloors' => $availableFloors,
            'availableUnitTypes' => $availableUnitTypes,
        ];
    }

    public function getBuildingDetails(Building $building): array
    {
        $building->load(['property', 'caretaker', 'units']);

        $unitStats = [
            'total' => $building->units->count(),
            'occupied' => $building->units->where('status', 'occupied')->count(),
            'vacant' => $building->units->where('status', 'vacant')->count(),
            'maintenance' => $building->units->where('status', 'maintenance')->count(),
        ];
        $unitStats['occupancy_rate'] = $unitStats['total'] > 0
            ? round(($unitStats['occupied'] / $unitStats['total']) * 100)
            : 0;

        $siblingBuildings = Building::where('property_id', $building->property_id)
            ->withCount('units')
            ->withCount(['units as occupied_units_count' => function ($q) {
                $q->where('status', 'occupied');
            }])
            ->get()
            ->map(function ($b) {
                $b->occupancy_rate = $b->units_count > 0
                    ? round(($b->occupied_units_count / $b->units_count) * 100)
                    : 0;

                return $b;
            });

        return [
            'building' => $building,
            'property' => $building->property,
            'siblingBuildings' => $siblingBuildings,
            'unitStats' => $unitStats,
            'buildingTypes' => Building::BUILDING_TYPES,
            'amenityOptions' => Building::AMENITY_OPTIONS,
            'activeAmenities' => $building->getActiveAmenities(),
        ];
    }

    public function bulkUpdateUnits(Building $building, array $unitIds, string $action, $value = null): void
    {
        $query = Unit::whereIn('id', $unitIds)->where('building_id', $building->id);

        match ($action) {
            'delete' => $query->delete(),
            'update_rent' => $query->update(['target_rent' => $value]),
            'update_type' => $query->update(['unit_type' => $value]),
            default => null,
        };
    }

    public function addUnit(Building $building, array $data, int $landlordId): Unit
    {
        $unit = Unit::create([
            'building_id' => $building->id,
            'landlord_id' => $landlordId,
            'floor_number' => (int) $data['floor_number'],
            'unit_number' => (string) $data['unit_number'],
            'target_rent' => $data['target_rent'],
            'unit_type' => $data['unit_type'],
            'status' => 'vacant',
        ]);

        if ((int) $data['floor_number'] > $building->total_floors) {
            $building->update(['total_floors' => (int) $data['floor_number']]);
        }

        return $unit;
    }

    public function inferPropertyType(string $buildingType): string
    {
        $commercial = ['office_block', 'warehouse', 'go_down', 'commercial_plaza'];
        $mixed = ['mixed_use'];

        if (in_array($buildingType, $commercial)) {
            return 'commercial';
        }
        if (in_array($buildingType, $mixed)) {
            return 'mixed';
        }

        return 'residential';
    }

    public function getArrearsInRange(Collection $leaseIds, int $minDays, int $maxDays): float
    {
        if ($leaseIds->isEmpty()) {
            return 0;
        }

        return Invoice::whereIn('lease_id', $leaseIds)
            ->whereIn('status', ['overdue', 'partial'])
            ->where('due_date', '<=', now()->subDays($minDays))
            ->where('due_date', '>=', now()->subDays($maxDays))
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0;
    }

    protected function applySorting($query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderBy('name', 'desc'),
            'occupancy_high' => $query->orderByRaw('(occupied_units_count * 100.0 / NULLIF(units_count, 0)) DESC'),
            'occupancy_low' => $query->orderByRaw('(occupied_units_count * 100.0 / NULLIF(units_count, 0)) ASC'),
            'updated' => $query->orderBy('updated_at', 'desc'),
            default => $query->orderBy('name', 'asc'),
        };
    }

    protected function getStartDate(string $period, Request $request): Carbon
    {
        return match ($period) {
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            'this_quarter' => now()->startOfQuarter(),
            'last_quarter' => now()->subQuarter()->startOfQuarter(),
            'this_year' => now()->startOfYear(),
            'custom' => Carbon::parse($request->get('start_date', now()->startOfMonth())),
            default => now()->startOfMonth(),
        };
    }

    protected function getEndDate(string $period, Request $request): Carbon
    {
        return match ($period) {
            'this_month' => now()->endOfMonth(),
            'last_month' => now()->subMonth()->endOfMonth(),
            'this_quarter' => now()->endOfQuarter(),
            'last_quarter' => now()->subQuarter()->endOfQuarter(),
            'this_year' => now()->endOfYear(),
            'custom' => Carbon::parse($request->get('end_date', now()->endOfMonth())),
            default => now()->endOfMonth(),
        };
    }

    protected function getFilteredUnits(Building $building, ?string $floor, ?string $unitType, ?string $status): Collection
    {
        $query = $building->units()
            ->with(['activeLease.tenant', 'activeLease.rentHistory'])
            ->orderBy('floor_number', 'desc')
            ->orderBy('unit_number', 'asc');

        if ($floor) {
            $query->where('floor_number', $floor);
        }
        if ($unitType) {
            $query->where('unit_type', $unitType);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->map(function ($unit) {
            $unit->color_class = match ($unit->status) {
                'occupied' => 'bg-green-50 border-green-200 text-green-700',
                'maintenance' => 'bg-orange-50 border-orange-200 text-orange-700',
                'arrears' => 'bg-red-50 border-red-200 text-red-700',
                default => 'bg-gray-50 border-gray-200 text-gray-400 hover:border-indigo-300',
            };

            return $unit;
        });
    }

    protected function getActionItems(Building $building, Collection $allUnits, Collection $leaseIds, Collection $allUnitIds): array
    {
        return [
            'overdue_invoices' => Invoice::whereIn('lease_id', $leaseIds)->where('status', 'overdue')->count(),
            'overdue_amount' => Invoice::whereIn('lease_id', $leaseIds)->where('status', 'overdue')
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
            'expiring_leases' => Lease::whereIn('unit_id', $allUnitIds)
                ->where('is_active', true)
                ->where('end_date', '<=', now()->addDays(30))
                ->where('end_date', '>=', now())
                ->count(),
            'urgent_tickets' => Ticket::where('building_id', $building->id)->open()->where('priority', 'urgent')->count(),
            'pending_readings' => WaterReading::whereIn('unit_id', $allUnitIds)->where('status', 'pending')->count(),
            'vacant_units' => $allUnits->where('status', 'vacant')->count(),
            'maintenance_units' => $allUnits->where('status', 'maintenance')->count(),
        ];
    }

    protected function getFinancialMetrics(Collection $unitIds, Collection $leaseIds, Carbon $startDate, Carbon $endDate): array
    {
        $expectedRevenue = Lease::whereIn('unit_id', $unitIds)->where('is_active', true)->sum('rent_amount');

        $periodRevenue = Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $collectionRate = $expectedRevenue > 0 ? round(($periodRevenue / $expectedRevenue) * 100, 1) : 0;

        $totalArrears = Invoice::whereIn('lease_id', $leaseIds)
            ->whereIn('status', ['overdue', 'partial'])
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0;

        return [
            'period_revenue' => $periodRevenue,
            'expected_revenue' => $expectedRevenue,
            'collection_rate' => $collectionRate,
            'total_arrears' => $totalArrears,
            'monthly_revenue' => $periodRevenue,
        ];
    }

    protected function getPeriodComparison(Collection $unitIds, Carbon $startDate, Carbon $endDate, Carbon $prevStartDate, Carbon $prevEndDate): array
    {
        $periodRevenue = Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $prevPeriodRevenue = Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->whereBetween('payment_date', [$prevStartDate, $prevEndDate])
            ->sum('amount');

        $revenueChange = $prevPeriodRevenue > 0
            ? round((($periodRevenue - $prevPeriodRevenue) / $prevPeriodRevenue) * 100, 1)
            : ($periodRevenue > 0 ? 100 : 0);

        return [
            'revenue' => [
                'current' => $periodRevenue,
                'previous' => $prevPeriodRevenue,
                'change' => $revenueChange,
                'trend' => $revenueChange >= 0 ? 'up' : 'down',
            ],
        ];
    }

    protected function getArrearsAging(Collection $leaseIds): array
    {
        return [
            '0_30' => $this->getArrearsInRange($leaseIds, 0, 30),
            '31_60' => $this->getArrearsInRange($leaseIds, 31, 60),
            '61_90' => $this->getArrearsInRange($leaseIds, 61, 90),
            '90_plus' => $this->getArrearsInRange($leaseIds, 91, 9999),
        ];
    }

    protected function getRecentPayments(Collection $unitIds, Carbon $startDate, Carbon $endDate): Collection
    {
        return Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->with(['invoice.lease.tenant', 'invoice.lease.unit'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    protected function getRecentTickets(int $buildingId): Collection
    {
        return Ticket::where('building_id', $buildingId)
            ->with(['building', 'unit', 'reporter'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    protected function getExpiringLeases(Collection $unitIds): Collection
    {
        return Lease::whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->with(['unit.building', 'tenant'])
            ->where('end_date', '<=', now()->addDays(60))
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'asc')
            ->limit(5)
            ->get();
    }
}
