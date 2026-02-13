<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PlatformFeeTier;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Traits\DatabaseAgnosticQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardService
{
    use DatabaseAgnosticQueries;

    public function getSuperAdminMetrics(): array
    {
        return FinanceCacheService::rememberSuperAdminStats('metrics', function () {
            $systemHealth = [
                'active_landlords' => User::where('role', 'landlord')->count(),
                'total_properties' => Property::withoutGlobalScope('landlord')->count(),
                'total_units' => Unit::withoutGlobalScope('landlord')->count(),
                'total_tenants' => User::where('role', 'tenant')->count(),
                'monthly_revenue' => Payment::withoutGlobalScope('landlord')
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount'),
                'total_revenue' => Payment::withoutGlobalScope('landlord')->withArchived()->sum('amount'),
            ];

            $actionItems = [
                'inactive_landlords' => User::where('role', 'landlord')
                    ->whereDoesntHave('properties')
                    ->count(),
                'new_signups' => User::where('role', 'landlord')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ];

            $landlords = User::where('role', 'landlord')
                ->withCount(['properties'])
                ->select(['users.id', 'users.name', 'users.email', 'users.created_at'])
                ->selectSub(
                    Unit::withoutGlobalScope('landlord')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('landlord_id', 'users.id'),
                    'units_count'
                )
                ->selectSub(
                    Unit::withoutGlobalScope('landlord')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('landlord_id', 'users.id')
                        ->where('status', 'occupied'),
                    'occupied_units'
                )
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $landlordIds = $landlords->pluck('id');
            $monthlyRevenues = $this->getLandlordsMonthlyRevenue($landlordIds);
            $landlords->each(fn ($l) => $l->monthly_revenue = $monthlyRevenues[$l->id] ?? 0);

            $month = (int) now()->format('m');
            $year = (int) now()->format('Y');
            $monthSql = $this->getMonthSql('p.payment_date');
            $yearSql = $this->getYearSql('p.payment_date');

            $topLandlords = User::where('role', 'landlord')
                ->select(['users.id', 'users.name', 'users.email', 'users.created_at'])
                ->selectRaw("COALESCE((
                    SELECT SUM(p.amount)
                    FROM payments p
                    INNER JOIN leases l ON p.lease_id = l.id
                    INNER JOIN units u ON l.unit_id = u.id
                    WHERE u.landlord_id = users.id
                    AND {$monthSql} = ?
                    AND {$yearSql} = ?
                ), 0) as monthly_revenue", [$month, $year])
                ->orderByDesc('monthly_revenue')
                ->limit(5)
                ->get();

            return [
                'systemHealth' => $systemHealth,
                'actionItems' => $actionItems,
                'landlords' => $landlords,
                'topLandlords' => $topLandlords,
            ];
        });
    }

    public function getLandlordDashboardData(User $landlord, Request $request): array
    {
        $allProperties = $landlord->properties()
            ->select(['id', 'landlord_id', 'name'])
            ->with(['buildings' => function ($query) {
                $query->whereNull('parent_building_id')
                    ->select(['id', 'property_id', 'parent_building_id', 'name', 'is_wing', 'unit_prefix', 'total_floors', 'units_per_floor'])
                    ->with(['wings' => function ($q) {
                        $q->select(['id', 'property_id', 'parent_building_id', 'name', 'is_wing', 'unit_prefix']);
                    }]);
            }])
            ->get();

        if ($allProperties->isEmpty()) {
            return ['redirect' => 'onboarding'];
        }

        $propertyId = $request->get('property_id');
        $property = $propertyId
            ? ($allProperties->firstWhere('id', $propertyId) ?? $allProperties->first())
            : $allProperties->first();

        $mainBuildings = $property->buildings->filter(fn ($b) => ! $b->is_wing);
        if ($mainBuildings->isEmpty()) {
            return ['redirect' => 'onboarding'];
        }

        $buildingId = $request->get('building_id');
        $activeBuilding = $buildingId
            ? ($mainBuildings->firstWhere('id', $buildingId) ?? $mainBuildings->first())
            : $mainBuildings->first();

        $wings = $activeBuilding->wings()
            ->select(['id', 'property_id', 'parent_building_id', 'name', 'is_wing', 'unit_prefix'])
            ->with(['units' => function ($q) {
                $q->select(['id', 'building_id', 'unit_number', 'floor_number', 'status', 'target_rent']);
            }])
            ->get();
        $hasWings = $wings->isNotEmpty();

        $wingId = $request->get('wing_id');
        $floorFilter = $request->get('floor');

        $allUnits = $this->getAllUnitsWithColorClass($activeBuilding);
        $filteredUnits = $this->filterUnits($allUnits, $wingId, $floorFilter);
        $allFloors = $allUnits->pluck('floor_number')->unique()->sortDesc()->values()->toArray();

        $metricsData = $this->calculateLandlordMetrics(
            $allUnits,
            $wings,
            $activeBuilding,
            $hasWings,
            $wingId,
            $floorFilter
        );

        $unitsByWing = $this->organizeUnitsByWing($allUnits, $wings, $hasWings);

        $allTiers = PlatformFeeTier::active()->ordered()->get();
        $currentTier = null;
        $mtdVolume = 0;

        if ($allTiers->isNotEmpty()) {
            $mtdVolume = (float) Payment::where('landlord_id', $landlord->id)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->where('is_voided', false)
                ->sum('amount');
            $currentTier = PlatformFeeTier::forVolume($mtdVolume);
        }

        return [
            'properties' => $allProperties,
            'property' => $property,
            'buildings' => $mainBuildings->values(),
            'activeBuilding' => $activeBuilding,
            'wings' => $wings,
            'hasWings' => $hasWings,
            'activeWingId' => $wingId ? (int) $wingId : null,
            'activeFloor' => $floorFilter ? (int) $floorFilter : null,
            'allFloors' => $allFloors,
            'units' => $filteredUnits->values(),
            'allUnits' => $allUnits,
            'unitsByWing' => $unitsByWing,
            'actionItems' => $metricsData['actionItems'],
            'financialMetrics' => $metricsData['financialMetrics'],
            'arrearsAging' => $metricsData['arrearsAging'],
            'stats' => $metricsData['stats'],
            'recentPayments' => $metricsData['recentPayments'],
            'recentTickets' => $metricsData['recentTickets'],
            'expiringLeases' => $metricsData['expiringLeases'],
            'tenantKycStats' => $metricsData['tenantKycStats'],
            'currentTier' => $currentTier,
            'mtdVolume' => $mtdVolume,
            'allTiers' => $allTiers,
        ];
    }

    public function getCaretakerDashboardData(User $caretaker): array
    {
        $property = Property::where('landlord_id', $caretaker->landlord_id)
            ->select(['id', 'landlord_id', 'name'])
            ->first();
        $assignedBuildings = $caretaker->assignedBuildings()
            ->select(['buildings.id', 'buildings.property_id', 'buildings.name', 'buildings.water_billing_type'])
            ->with(['units' => function ($q) {
                $q->select(['id', 'building_id', 'status']);
            }])
            ->get();

        if ($assignedBuildings->isEmpty() && ! $property) {
            return [
                'property' => ['name' => 'No Property Assigned'],
                'buildings' => [],
                'actionItems' => ['urgent_tickets' => 0, 'open_tickets' => 0, 'pending_readings' => 0],
                'ticketStats' => ['total' => 0, 'open' => 0, 'urgent' => 0, 'resolved' => 0],
                'unitStats' => ['total' => 0, 'occupied' => 0, 'vacant' => 0, 'maintenance' => 0],
                'todaysTasks' => [],
                'hasWaterEnabled' => false,
                'landlord' => null,
            ];
        }

        $buildingIds = $assignedBuildings->pluck('id');

        $actionItems = [
            'urgent_tickets' => Ticket::where('assigned_to', $caretaker->id)
                ->open()
                ->where('priority', 'urgent')
                ->count(),
            'open_tickets' => Ticket::where('assigned_to', $caretaker->id)->open()->count(),
            'pending_readings' => WaterReading::whereIn('unit_id', function ($query) use ($buildingIds) {
                $query->select('id')->from('units')->whereIn('building_id', $buildingIds);
            })->where('status', 'pending')->count(),
        ];

        $ticketStats = [
            'total' => Ticket::where('assigned_to', $caretaker->id)->count(),
            'open' => Ticket::where('assigned_to', $caretaker->id)->open()->count(),
            'urgent' => Ticket::where('assigned_to', $caretaker->id)->open()->where('priority', 'urgent')->count(),
            'resolved' => Ticket::where('assigned_to', $caretaker->id)->where('status', 'resolved')->count(),
        ];

        $todaysTasks = Ticket::where('assigned_to', $caretaker->id)
            ->open()
            ->select(['id', 'title', 'description', 'priority', 'status', 'building_id', 'unit_id', 'reporter_id'])
            ->with([
                'building:id,name',
                'unit:id,unit_number',
                'reporter:id,name',
            ])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->limit(10)
            ->get();

        $unitStats = [
            'total' => $assignedBuildings->sum(fn ($b) => $b->units->count()),
            'occupied' => $assignedBuildings->sum(fn ($b) => $b->units->where('status', 'occupied')->count()),
            'vacant' => $assignedBuildings->sum(fn ($b) => $b->units->where('status', 'vacant')->count()),
            'maintenance' => $assignedBuildings->sum(fn ($b) => $b->units->where('status', 'maintenance')->count()),
        ];

        $hasWaterEnabled = $assignedBuildings->contains(fn ($building) => $building->hasWaterEnabled());

        return [
            'property' => $property,
            'buildings' => $assignedBuildings,
            'actionItems' => $actionItems,
            'ticketStats' => $ticketStats,
            'todaysTasks' => $todaysTasks,
            'unitStats' => $unitStats,
            'hasWaterEnabled' => $hasWaterEnabled,
            'landlord' => $caretaker->landlord ? [
                'name' => $caretaker->landlord->name,
                'email' => $caretaker->landlord->email,
                'mobile_number' => $caretaker->landlord->mobile_number,
            ] : null,
        ];
    }

    public function getTenantDashboardData(User $tenant): array
    {
        $lease = $tenant->lease()->with(['unit.building', 'rentHistory'])->first();

        if (! $lease) {
            return $this->getTenantNoleaseData($tenant);
        }

        $unit = $lease->unit;
        $building = $unit->building;

        $totalInvoiced = Invoice::where('lease_id', $lease->id)->sum('total_due');
        $totalPaid = Payment::withArchived()->where('lease_id', $lease->id)->sum('amount');
        $balance = $totalPaid - $totalInvoiced;

        $actionItems = $this->getTenantActionItems($tenant, $lease);

        $nextPayment = Invoice::where('lease_id', $lease->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->first();

        $recentPayments = Payment::where('lease_id', $lease->id)
            ->select(['id', 'amount', 'payment_method', 'payment_date'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentTickets = Ticket::where('reporter_id', $tenant->id)
            ->select(['id', 'title', 'status', 'priority', 'building_id', 'created_at'])
            ->with('building:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $pendingInvoices = Invoice::where('lease_id', $lease->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->select(['id', 'invoice_number', 'total_due', 'amount_paid', 'due_date', 'status'])
            ->orderBy('due_date', 'asc')
            ->get();

        $caretaker = $building->caretaker;

        return [
            'hasLease' => true,
            'unit' => [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'floor_number' => $unit->floor_number,
                'status' => $unit->status,
            ],
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
            ],
            'lease' => [
                'id' => $lease->id,
                'rent_amount' => $lease->rent_amount,
                'deposit_amount' => $lease->deposit_amount,
                'start_date' => $lease->start_date,
                'end_date' => $lease->end_date,
            ],
            'balance' => $balance,
            'actionItems' => $actionItems,
            'nextPayment' => $nextPayment,
            'recentPayments' => $recentPayments,
            'recentTickets' => $recentTickets,
            'pendingInvoices' => $pendingInvoices,
            'caretaker' => $caretaker ? [
                'name' => $caretaker->name,
                'mobile_number' => $caretaker->mobile_number,
            ] : null,
        ];
    }

    public function getArrearsInRange(int $minDays, int $maxDays): float
    {
        return Invoice::whereIn('status', ['overdue', 'partial'])
            ->where('due_date', '<=', now()->subDays($minDays))
            ->where('due_date', '>=', now()->subDays($maxDays))
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0;
    }

    public function getArrearsInRangeForLeases(Collection $leaseIds, int $minDays, int $maxDays): float
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

    protected function getLandlordMonthlyRevenue(int $landlordId): float
    {
        return Payment::withoutGlobalScope('landlord')
            ->whereHas('lease', function ($q) use ($landlordId) {
                $q->whereHas('unit', function ($q2) use ($landlordId) {
                    $q2->where('landlord_id', $landlordId);
                });
            })
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');
    }

    protected function getLandlordsMonthlyRevenue(Collection $landlordIds): Collection
    {
        if ($landlordIds->isEmpty()) {
            return collect();
        }

        return Payment::withoutGlobalScope('landlord')
            ->select('units.landlord_id')
            ->selectRaw('SUM(payments.amount) as monthly_revenue')
            ->join('leases', 'payments.lease_id', '=', 'leases.id')
            ->join('units', 'leases.unit_id', '=', 'units.id')
            ->whereIn('units.landlord_id', $landlordIds)
            ->whereMonth('payments.payment_date', now()->month)
            ->whereYear('payments.payment_date', now()->year)
            ->groupBy('units.landlord_id')
            ->pluck('monthly_revenue', 'landlord_id');
    }

    protected function getAllUnitsWithColorClass(Building $building): Collection
    {
        return $building->allUnits()
            ->select(['id', 'building_id', 'unit_number', 'floor_number', 'status', 'target_rent'])
            ->with([
                'activeLease' => function ($q) {
                    $q->select(['id', 'unit_id', 'tenant_id', 'rent_amount']);
                },
                'activeLease.tenant:id,name,email',
                'building:id,name,unit_prefix,is_wing',
            ])
            ->orderBy('floor_number', 'desc')
            ->orderBy('unit_number', 'asc')
            ->get()
            ->map(function ($unit) {
                $unit->color_class = match ($unit->status) {
                    'occupied' => 'bg-green-50 border-green-200 text-green-700',
                    'maintenance' => 'bg-orange-50 border-orange-200 text-orange-700',
                    'arrears' => 'bg-red-50 border-red-200 text-red-700',
                    default => 'bg-gray-50 border-gray-200 text-gray-400 hover:border-indigo-300',
                };
                $unit->wing_name = $unit->building->is_wing ? $unit->building->name : null;

                return $unit;
            });
    }

    protected function filterUnits(Collection $units, ?string $wingId, ?string $floorFilter): Collection
    {
        $filtered = $units;

        if ($wingId) {
            $filtered = $filtered->where('building_id', $wingId);
        }

        if ($floorFilter) {
            $filtered = $filtered->where('floor_number', $floorFilter);
        }

        return $filtered;
    }

    protected function calculateLandlordMetrics(
        Collection $allUnits,
        Collection $wings,
        Building $activeBuilding,
        bool $hasWings,
        ?string $wingId,
        ?string $floorFilter
    ): array {
        if ($wingId) {
            $metricsUnits = $allUnits->where('building_id', (int) $wingId);
            $metricsBuildingIds = [(int) $wingId];
        } elseif ($floorFilter) {
            $metricsUnits = $allUnits->where('floor_number', (int) $floorFilter);
            $metricsBuildingIds = $hasWings
                ? $wings->pluck('id')->toArray()
                : [$activeBuilding->id];
        } else {
            $metricsUnits = $allUnits;
            $metricsBuildingIds = $hasWings
                ? array_merge([$activeBuilding->id], $wings->pluck('id')->toArray())
                : [$activeBuilding->id];
        }

        $metricsUnitIds = $metricsUnits->pluck('id');
        $metricsLeaseIds = Lease::whereIn('unit_id', $metricsUnitIds)->pluck('id');

        $overdueStats = Invoice::whereIn('lease_id', $metricsLeaseIds)
            ->where('status', 'overdue')
            ->selectRaw('COUNT(*) as overdue_count, COALESCE(SUM(total_due - amount_paid), 0) as overdue_amount')
            ->first();

        $actionItems = [
            'overdue_invoices' => (int) ($overdueStats->overdue_count ?? 0),
            'overdue_amount' => (float) ($overdueStats->overdue_amount ?? 0),
            'expiring_leases' => Lease::whereIn('unit_id', $metricsUnitIds)
                ->where('is_active', true)
                ->where('end_date', '<=', now()->addDays(30))
                ->where('end_date', '>=', now())
                ->count(),
            'urgent_tickets' => Ticket::whereIn('building_id', $metricsBuildingIds)->open()->where('priority', 'urgent')->count(),
            'pending_readings' => WaterReading::whereIn('unit_id', $metricsUnitIds)->where('status', 'pending')->count(),
            'vacant_units' => $metricsUnits->where('status', 'vacant')->count(),
            'maintenance_units' => $metricsUnits->where('status', 'maintenance')->count(),
        ];

        $expectedRevenue = Lease::whereIn('unit_id', $metricsUnitIds)->where('is_active', true)->sum('rent_amount');
        $monthlyRevenue = Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $metricsUnitIds))
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $collectionRate = $expectedRevenue > 0 ? round(($monthlyRevenue / $expectedRevenue) * 100, 1) : 0;

        $financialMetrics = [
            'monthly_revenue' => $monthlyRevenue,
            'expected_revenue' => $expectedRevenue,
            'collection_rate' => $collectionRate,
            'total_arrears' => Invoice::whereIn('lease_id', $metricsLeaseIds)
                ->whereIn('status', ['overdue', 'partial'])
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
        ];

        $arrearsAging = [
            '0_30' => $this->getArrearsInRangeForLeases($metricsLeaseIds, 0, 30),
            '31_60' => $this->getArrearsInRangeForLeases($metricsLeaseIds, 31, 60),
            '61_90' => $this->getArrearsInRangeForLeases($metricsLeaseIds, 61, 90),
            '90_plus' => $this->getArrearsInRangeForLeases($metricsLeaseIds, 91, 9999),
        ];

        $stats = [
            'total_units' => $metricsUnits->count(),
            'occupied_units' => $metricsUnits->where('status', 'occupied')->count(),
            'vacant_units' => $metricsUnits->where('status', 'vacant')->count(),
            'arrears_units' => $metricsUnits->where('status', 'arrears')->count(),
            'occupancy_rate' => $metricsUnits->count() > 0
                ? round(($metricsUnits->where('status', 'occupied')->count() / $metricsUnits->count()) * 100)
                : 0,
        ];

        $recentPayments = Payment::whereHas('lease', fn ($q) => $q->whereIn('unit_id', $metricsUnitIds))
            ->select(['id', 'invoice_id', 'amount', 'payment_method', 'payment_date', 'created_at'])
            ->with([
                'invoice:id,lease_id',
                'invoice.lease:id,tenant_id,unit_id',
                'invoice.lease.tenant:id,name',
                'invoice.lease.unit:id,unit_number,building_id',
                'invoice.lease.unit.building:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentTickets = Ticket::whereIn('building_id', $metricsBuildingIds)
            ->select(['id', 'title', 'category', 'subcategory', 'priority', 'status', 'building_id', 'unit_id', 'reporter_id', 'created_at'])
            ->with(['building:id,name', 'unit:id,unit_number', 'reporter:id,name,role,profile_photo_path'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'category' => $ticket->category,
                'subcategory' => $ticket->subcategory,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'reporter_name' => $ticket->reporter?->name,
                'reporter_photo' => $ticket->reporter?->profile_photo_url ?? null,
                'unit_number' => $ticket->unit?->unit_number,
                'building_name' => $ticket->building?->name,
                'created_at' => $ticket->created_at,
                'is_tenant_submitted' => $ticket->reporter?->isTenant() ?? false,
            ]);

        $expiringLeases = Lease::whereIn('unit_id', $metricsUnitIds)
            ->where('is_active', true)
            ->where('end_date', '<=', now()->addDays(60))
            ->where('end_date', '>=', now())
            ->select(['id', 'tenant_id', 'unit_id', 'end_date'])
            ->with([
                'tenant:id,name',
                'unit:id,unit_number,building_id',
                'unit.building:id,name',
            ])
            ->orderBy('end_date')
            ->limit(5)
            ->get();

        $tenantKycStats = $this->getTenantKycStats($metricsLeaseIds);

        return [
            'actionItems' => $actionItems,
            'financialMetrics' => $financialMetrics,
            'arrearsAging' => $arrearsAging,
            'stats' => $stats,
            'recentPayments' => $recentPayments,
            'recentTickets' => $recentTickets,
            'expiringLeases' => $expiringLeases,
            'tenantKycStats' => $tenantKycStats,
        ];
    }

    protected function organizeUnitsByWing(Collection $allUnits, Collection $wings, bool $hasWings): array
    {
        $unitsByWing = [];

        if ($hasWings) {
            foreach ($wings as $wing) {
                $wingUnits = $allUnits->where('building_id', $wing->id)->values();
                $unitsByWing[] = [
                    'wing' => $wing,
                    'units' => $wingUnits,
                    'floors' => $wingUnits->pluck('floor_number')->unique()->sortDesc()->values(),
                ];
            }
        } else {
            $unitsByWing[] = [
                'wing' => null,
                'units' => $allUnits,
                'floors' => $allUnits->pluck('floor_number')->unique()->sortDesc()->values(),
            ];
        }

        return $unitsByWing;
    }

    protected function getTenantNoLeaseData(User $tenant): array
    {
        $pendingInvitations = TenantInvitation::valid()
            ->where('existing_user_id', $tenant->id)
            ->select(['id', 'unit_id', 'landlord_id', 'rent_amount', 'service_charge', 'deposit_amount', 'start_date', 'end_date', 'expires_at'])
            ->with([
                'unit:id,unit_number,floor_number,building_id',
                'unit.building:id,name,property_id',
                'unit.building.property:id,name',
                'landlord:id,name',
            ])
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'property_name' => $invitation->unit->building->property->name ?? 'Unknown Property',
                    'building_name' => $invitation->unit->building->name ?? 'Unknown Building',
                    'unit_number' => $invitation->unit->unit_number,
                    'floor_number' => $invitation->unit->floor_number,
                    'rent_amount' => $invitation->rent_amount,
                    'service_charge' => $invitation->service_charge,
                    'deposit_amount' => $invitation->deposit_amount,
                    'total_move_in' => $invitation->total_move_in_cost,
                    'start_date' => $invitation->start_date->format('M d, Y'),
                    'end_date' => $invitation->end_date?->format('M d, Y'),
                    'expires_at' => $invitation->expires_at->format('M d, Y'),
                    'landlord_name' => $invitation->landlord->name ?? 'Unknown',
                ];
            });

        return [
            'hasLease' => false,
            'message' => $pendingInvitations->isEmpty()
                ? 'You do not have an active lease. Please contact your landlord.'
                : null,
            'pendingInvitations' => $pendingInvitations,
        ];
    }

    protected function getTenantActionItems(User $tenant, $lease): array
    {
        $overdueDate = Invoice::where('lease_id', $lease->id)
            ->where('status', 'overdue')
            ->orderBy('due_date', 'asc')
            ->value('due_date');

        return [
            'pending_invoices' => Invoice::where('lease_id', $lease->id)
                ->whereIn('status', ['sent', 'partial'])
                ->count(),
            'overdue_invoices' => Invoice::where('lease_id', $lease->id)
                ->where('status', 'overdue')
                ->count(),
            'overdue_days' => $overdueDate ? now()->diffInDays($overdueDate) : 0,
            'open_tickets' => Ticket::where('reporter_id', $tenant->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
        ];
    }

    public function getTenantKycStats(Collection $leaseIds): array
    {
        if ($leaseIds->isEmpty()) {
            return ['total' => 0, 'complete' => 0, 'incomplete' => 0, 'rate' => 0];
        }

        $tenants = User::whereIn('id', function ($q) use ($leaseIds) {
            $q->select('tenant_id')
                ->from('leases')
                ->whereIn('id', $leaseIds)
                ->where('is_active', true);
        })->get(['id', 'mobile_number', 'national_id', 'emergency_contact_name', 'emergency_contact_phone', 'profile_photo_path', 'kyc_completed_at']);

        $total = $tenants->count();
        $complete = $tenants->filter(fn ($t) => $t->hasCompletedKyc())->count();

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $total - $complete,
            'rate' => $total > 0 ? round(($complete / $total) * 100) : 0,
        ];
    }

    public function getUnitDetailData(Unit $unit): array
    {
        $lease = $unit->activeLease()->with([
            'tenant:id,name,email,mobile_number,emergency_contact_name,emergency_contact_phone,profile_photo_path,kyc_completed_at',
        ])->first();

        $tenant = $lease?->tenant;

        $unitTickets = Ticket::where('unit_id', $unit->id)
            ->with('reporter:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'status', 'priority', 'created_at', 'reporter_id']);

        $leasePayments = $lease
            ? Payment::where('lease_id', $lease->id)
                ->orderBy('payment_date', 'desc')
                ->limit(5)
                ->get(['id', 'amount', 'payment_method', 'payment_date', 'reference'])
            : collect();

        return [
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->mobile_number,
                'emergency_contact' => $tenant->emergency_contact_name
                    ? "{$tenant->emergency_contact_name} ({$tenant->emergency_contact_phone})"
                    : null,
                'profile_photo_url' => $tenant->profile_photo_url ?? null,
                'kyc_complete' => $tenant->hasCompletedKyc(),
                'kyc_completed_at' => $tenant->kyc_completed_at,
            ] : null,
            'tickets' => $unitTickets,
            'payments' => $leasePayments,
        ];
    }

    /**
     * Calculate quick metrics for real-time broadcast updates.
     * Used by PaymentReceived event to include updated metrics in payload.
     */
    public function calculateQuickMetrics(int $landlordId): array
    {
        $leaseIds = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->pluck('id');

        $expectedRevenue = Lease::whereIn('id', $leaseIds)->sum('rent_amount');

        $monthlyRevenue = Payment::whereHas('invoice.lease', fn ($q) => $q->whereIn('id', $leaseIds))
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $collectionRate = $expectedRevenue > 0
            ? round(($monthlyRevenue / $expectedRevenue) * 100, 1)
            : 0;

        $totalArrears = Invoice::whereHas('lease', fn ($q) => $q->whereIn('id', $leaseIds))
            ->whereIn('status', ['overdue', 'partial'])
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0;

        return [
            'financial' => [
                'monthly_revenue' => (float) $monthlyRevenue,
                'expected_revenue' => (float) $expectedRevenue,
                'collection_rate' => $collectionRate,
                'total_arrears' => (float) $totalArrears,
            ],
            'arrears_aging' => [
                '0_30' => $this->getArrearsInRangeForLeases($leaseIds, 0, 30),
                '31_60' => $this->getArrearsInRangeForLeases($leaseIds, 31, 60),
                '61_90' => $this->getArrearsInRangeForLeases($leaseIds, 61, 90),
                '90_plus' => $this->getArrearsInRangeForLeases($leaseIds, 91, 9999),
            ],
        ];
    }
}
