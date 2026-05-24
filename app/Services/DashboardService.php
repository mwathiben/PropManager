<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\LandlordDashboard;
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
use Illuminate\Support\Facades\Log;

class DashboardService
{
    use DatabaseAgnosticQueries;

    /**
     * OBS-15: bracket a dashboard section with hrtime() so a slow
     * query can be traced back to the section that owns it. Logs at
     * debug level on the metrics channel; sample the log in prod by
     * adjusting METRICS_LOG_LEVEL or letting the channel daily-roll.
     */
    private function trackSection(string $section, ?int $landlordId, callable $work): mixed
    {
        $start = hrtime(true);
        try {
            return $work();
        } finally {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            Log::channel(config('logging.metrics_channel', 'stack'))->debug(
                'dashboard section duration',
                [
                    'section' => $section,
                    'landlord_id' => $landlordId,
                    'duration_ms' => $durationMs,
                ]
            );
        }
    }

    public function getSuperAdminMetrics(): array
    {
        // PERF-R4: include the current month-year in the cache key so the
        // super-admin dashboard doesn't show last month's revenue for up
        // to 5 minutes after midnight on the 1st (the queries below are
        // bound to {now()->month, now()->year}, but the cache key wasn't).
        $monthSuffix = now()->format('Y-m');

        return FinanceCacheService::rememberSuperAdminStats("metrics:{$monthSuffix}", function () {
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
        return $this->trackSection('landlord_dashboard', (int) $landlord->id, function () use ($landlord, $request) {
            return $this->buildLandlordDashboardData($landlord, $request);
        });
    }

    /**
     * Phase-105 PORTFOLIO-HOME: the landlord's landing — a cross-property overview
     * (portfolio KPIs + per-property cards that drill into a building dashboard) instead of
     * defaulting straight into one building. Reuses PropertyMetricsService::forLandlord (no
     * N+1) + landlord-scoped action counts. Building-scoped detail stays in
     * getLandlordDashboardData (rendered when a building_id is chosen).
     *
     * @return array{redirect?: string, kpis?: array<string, mixed>, actions?: array<string, mixed>, properties?: array<int, array<string, mixed>>}
     */
    public function getPortfolioOverview(User $landlord): array
    {
        $landlordId = (int) $landlord->id;

        $rows = app(\App\Services\Property\PropertyMetricsService::class)->forLandlord($landlordId);
        if ($rows === []) {
            return ['redirect' => 'onboarding'];
        }

        // The first main (non-wing) building per property → a one-click drill target into the
        // existing building dashboard. One grouped query, no N+1.
        $primaryBuildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->whereNull('parent_building_id')
            ->orderBy('id')
            ->get(['id', 'property_id'])
            ->groupBy('property_id')
            ->map(fn ($group) => (int) $group->first()->id);

        $unitTotal = array_sum(array_column($rows, 'unit_count'));
        $occupiedTotal = array_sum(array_column($rows, 'occupied_count'));

        $properties = array_map(function (array $row) use ($primaryBuildings) {
            $row['primary_building_id'] = $primaryBuildings[$row['property_id']] ?? null;

            return $row;
        }, $rows);

        // Surface the at-risk first: highest outstanding arrears, then lowest occupancy.
        usort($properties, function (array $a, array $b) {
            return [$b['outstanding_arrears'], $a['occupancy_pct']] <=> [$a['outstanding_arrears'], $b['occupancy_pct']];
        });

        return [
            'kpis' => [
                'property_count' => count($rows),
                'building_count' => (int) array_sum(array_column($rows, 'building_count')),
                'unit_count' => (int) $unitTotal,
                'occupied_count' => (int) $occupiedTotal,
                'vacant_count' => (int) ($unitTotal - $occupiedTotal),
                // Portfolio occupancy is unit-weighted (not a naive average of per-property %).
                'occupancy_pct' => $unitTotal > 0 ? round($occupiedTotal / $unitTotal * 100, 1) : 0.0,
                'monthly_rent_roll' => round((float) array_sum(array_column($rows, 'monthly_rent_roll')), 2),
                'outstanding_arrears' => round((float) array_sum(array_column($rows, 'outstanding_arrears')), 2),
            ],
            'actions' => $this->portfolioActionSummary($landlordId),
            'properties' => $properties,
        ];
    }

    /**
     * Landlord-wide action counts for the portfolio header (explicitly landlord-scoped).
     *
     * @return array{overdue_invoices: int, overdue_amount: float, open_tickets: int, expiring_leases: int}
     */
    private function portfolioActionSummary(int $landlordId): array
    {
        $overdue = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_due - amount_paid), 0) as amount')
            ->first();

        return [
            'overdue_invoices' => (int) ($overdue->cnt ?? 0),
            'overdue_amount' => round((float) ($overdue->amount ?? 0), 2),
            'open_tickets' => Ticket::where('landlord_id', $landlordId)->open()->count(),
            'expiring_leases' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now(), now()->addDays(60)])
                ->count(),
        ];
    }

    private function buildLandlordDashboardData(User $landlord, Request $request): array
    {
        $allProperties = $landlord->properties()
            ->select(['id', 'landlord_id', 'name'])
            ->with(['buildings' => function ($query) {
                $query->whereNull('parent_building_id')
                    ->select(['id', 'landlord_id', 'property_id', 'parent_building_id', 'name', 'is_wing', 'unit_prefix', 'total_floors', 'units_per_floor'])
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

        // Phase-74 CROSS-BUILDING: an explicit ?building_id wins for a one-off
        // view; otherwise fall back to the landlord's persisted scope preference
        // (only meaningful when they own more than one building).
        $buildingId = $request->get('building_id');
        $dashboardScope = $this->resolveScope($landlord);
        if ($buildingId === 'all') {
            $allBuildingsMode = true;
        } elseif ($buildingId !== null) {
            $allBuildingsMode = false;
        } else {
            $allBuildingsMode = $dashboardScope === 'all_buildings' && $mainBuildings->count() > 1;
        }
        $activeBuilding = ($buildingId && $buildingId !== 'all')
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

        [$crossBuildingUnits, $crossBuildingIds] = $allBuildingsMode
            ? $this->getCrossBuildingMetricsContext($mainBuildings)
            : [null, []];

        $metricsData = $this->calculateLandlordMetrics(
            $allUnits,
            $wings,
            $activeBuilding,
            $hasWings,
            $wingId,
            $floorFilter,
            $allBuildingsMode,
            $crossBuildingUnits,
            $crossBuildingIds,
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
            'allBuildingsMode' => $allBuildingsMode,
            'dashboardScope' => $dashboardScope,
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
            // Phase-36 INSIGHT-LANDLORD-1: composite growth signals
            // (engagement / referrals / usage ratios). Fail-soft —
            // any error here returns null so the dashboard never
            // 500s on a missing engagement row.
            'growth' => $this->landlordGrowthSummary($landlord),
            // Phase-55 WIDGET-ORDERING: landlord-preferred ordering of the
            // bottom-row widgets, persisted via slug='main_dashboard' on
            // landlord_dashboards. Falls through to the canonical default
            // when no row exists yet.
            'widgetOrder' => $this->resolveWidgetOrder($landlord),
        ];
    }

    /**
     * Phase-55 WIDGET-ORDERING-3: read the landlord's saved widget order
     * (slug='main_dashboard'). Sanitise on read so a layout row that
     * predates the allowed-widget contract can't break the dashboard;
     * the validator on PATCH /dashboards/preferences enforces the same
     * allow-list on write.
     *
     * @return array<int, string>
     */
    protected function resolveWidgetOrder(User $landlord): array
    {
        $allowed = \App\Http\Controllers\DashboardPreferenceController::ALLOWED_WIDGETS;
        $row = LandlordDashboard::query()
            ->where('landlord_id', $landlord->id)
            ->where('slug', \App\Http\Controllers\DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->first();

        if ($row === null || ! is_array($row->layout)) {
            return $allowed;
        }

        // Phase-74: layout is now {widgets, scope}; tolerate the legacy flat list.
        $widgets = \App\Http\Controllers\DashboardPreferenceController::widgetsFrom($row->layout);
        $sanitised = array_values(array_unique(array_intersect($widgets, $allowed)));
        $missing = array_values(array_diff($allowed, $sanitised));

        return array_merge($sanitised, $missing);
    }

    /**
     * Phase-74 CROSS-BUILDING-1: the landlord's persisted main-dashboard
     * building scope ('active_building' | 'all_buildings'), stored on the
     * main_dashboard row. Defaults to active_building when unset.
     */
    protected function resolveScope(User $landlord): string
    {
        $layout = LandlordDashboard::query()
            ->where('landlord_id', $landlord->id)
            ->where('slug', \App\Http\Controllers\DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->value('layout');

        return \App\Http\Controllers\DashboardPreferenceController::scopeFrom($layout);
    }

    /**
     * Phase-36 INSIGHT-LANDLORD-1: delegate to InsightDashboardService.
     */
    private function landlordGrowthSummary(User $landlord): ?array
    {
        try {
            return app(\App\Services\Insight\InsightDashboardService::class)
                ->landlordSummary($landlord->id);
        } catch (\Throwable) {
            return null;
        }
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

        // One pass over the caretaker's tickets, four CASE-branched counts.
        // Replaces 5 redundant COUNT queries (open and urgent counts were
        // computed twice for actionItems and ticketStats).
        $ticketCounts = Ticket::where('assigned_to', $caretaker->id)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('open', 'acknowledged', 'in_progress') THEN 1 END) as open_count,
                COUNT(CASE WHEN status IN ('open', 'acknowledged', 'in_progress') AND priority = 'urgent' THEN 1 END) as urgent_open_count,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count
            ")
            ->first();

        $actionItems = [
            'urgent_tickets' => (int) $ticketCounts->urgent_open_count,
            'open_tickets' => (int) $ticketCounts->open_count,
            'pending_readings' => WaterReading::whereIn('unit_id', function ($query) use ($buildingIds) {
                $query->select('id')->from('units')->whereIn('building_id', $buildingIds);
            })->where('status', 'pending')->count(),
        ];

        $ticketStats = [
            'total' => (int) $ticketCounts->total,
            'open' => (int) $ticketCounts->open_count,
            'urgent' => (int) $ticketCounts->urgent_open_count,
            'resolved' => (int) $ticketCounts->resolved_count,
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

        // Phase-79 DASHBOARD-WATER-3: gate caretaker water widgets on the same
        // module rule as the nav — the landlord actually CHARGES for water
        // (consumption/flat_rate), not merely a non-null billing type.
        $hasWaterEnabled = \App\Services\Water\WaterModuleAccess::enabledForLandlord((int) $caretaker->landlord_id);

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
            ->where('is_voided', false)
            ->select(['id', 'amount', 'payment_method', 'payment_date'])
            ->orderBy('payment_date', 'desc')
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

    /**
     * Compute all four arrears-aging buckets in ONE query instead of four
     * separate SUMs against the same row set. Saves 3 queries per landlord
     * dashboard render and per real-time payment broadcast.
     *
     * @return array{0_30: float, 31_60: float, 61_90: float, 90_plus: float}
     */
    public function getArrearsAgingBucketsForLeases(Collection $leaseIds): array
    {
        $empty = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];

        if ($leaseIds->isEmpty()) {
            return $empty;
        }

        $daysDiffSql = $this->getDaysBetweenSql('due_date', now()->format('Y-m-d'));

        $row = Invoice::whereIn('lease_id', $leaseIds)
            ->whereIn('status', ['overdue', 'partial'])
            ->whereNotNull('due_date')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 0 AND 30
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_0_30,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 31 AND 60
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_31_60,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 61 AND 90
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_61_90,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} > 90
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_90_plus
            ")
            ->first();

        return [
            '0_30' => round((float) $row->bucket_0_30, 2),
            '31_60' => round((float) $row->bucket_31_60, 2),
            '61_90' => round((float) $row->bucket_61_90, 2),
            '90_plus' => round((float) $row->bucket_90_plus, 2),
        ];
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

    /**
     * Phase-55 DASHBOARD-FILTERS-1: gather units + building ids across every main
     * building (and its wings) of the property so dashboard metrics can aggregate
     * landlord-wide when building_id is missing or set to the 'all' sentinel.
     *
     * @param  Collection<int, Building>  $mainBuildings
     * @return array{0: Collection, 1: array<int, int>}
     */
    protected function getCrossBuildingMetricsContext(Collection $mainBuildings): array
    {
        $buildingIds = [];
        foreach ($mainBuildings as $building) {
            $buildingIds[] = (int) $building->id;
            foreach ($building->wings ?? [] as $wing) {
                $buildingIds[] = (int) $wing->id;
            }
        }
        $buildingIds = array_values(array_unique($buildingIds));

        if ($buildingIds === []) {
            return [collect(), []];
        }

        $units = \App\Models\Unit::whereIn('building_id', $buildingIds)
            ->select(['id', 'building_id', 'unit_number', 'floor_number', 'status', 'target_rent'])
            ->get();

        return [$units, $buildingIds];
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
        ?string $floorFilter,
        bool $allBuildingsMode = false,
        ?Collection $crossBuildingUnits = null,
        array $crossBuildingIds = [],
    ): array {
        if ($allBuildingsMode && $crossBuildingUnits !== null) {
            $metricsUnits = $crossBuildingUnits;
            $metricsBuildingIds = $crossBuildingIds;
        } elseif ($wingId) {
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
        $metricsLeaseIds = Lease::withTrashed()->whereIn('unit_id', $metricsUnitIds)->pluck('id');

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
            // Phase-80 ESCALATION-VIEW-2: open caretaker escalations awaiting the landlord.
            'escalated_tickets' => Ticket::whereIn('building_id', $metricsBuildingIds)->escalated()->count(),
            // Phase-82 DOC-EXPIRY-2: renewable documents expiring within 30 days.
            'expiring_documents' => \App\Models\Document::query()
                ->where('landlord_id', (int) $activeBuilding->landlord_id)
                ->current()
                ->where('is_renewable', true)
                ->expiringSoon(30)
                ->count(),
            // Phase-79 DASHBOARD-WATER-2: water-reading review moved to the Water
            // hub; the landlord dashboard no longer surfaces (or computes) it.
            'vacant_units' => $metricsUnits->where('status', 'vacant')->count(),
            'maintenance_units' => $metricsUnits->where('status', 'maintenance')->count(),
        ];

        $expectedRevenue = Lease::whereIn('unit_id', $metricsUnitIds)->where('is_active', true)->sum('rent_amount');
        // PERF-Q7: $metricsLeaseIds is already computed above; using it as a
        // direct whereIn replaces the correlated `whereHas('lease', ...)`
        // EXISTS subquery with a flat IN against the indexed lease_id.
        $monthlyRevenue = Payment::whereIn('lease_id', $metricsLeaseIds)
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

        $arrearsAging = $this->getArrearsAgingBucketsForLeases($metricsLeaseIds);

        $stats = [
            'total_units' => $metricsUnits->count(),
            'occupied_units' => $metricsUnits->where('status', 'occupied')->count(),
            'vacant_units' => $metricsUnits->where('status', 'vacant')->count(),
            'arrears_units' => $metricsUnits->where('status', 'arrears')->count(),
            'occupancy_rate' => $metricsUnits->count() > 0
                ? round(($metricsUnits->where('status', 'occupied')->count() / $metricsUnits->count()) * 100)
                : 0,
        ];

        // PERF-Q7: same swap as monthlyRevenue — direct whereIn over the
        // pre-computed lease ids instead of a correlated EXISTS subquery.
        $recentPayments = Payment::whereIn('lease_id', $metricsLeaseIds)
            ->where('is_voided', false)
            ->select(['id', 'invoice_id', 'amount', 'payment_method', 'payment_date', 'created_at'])
            ->with([
                'invoice:id,lease_id',
                'invoice.lease' => function ($q) {
                    $q->withTrashed()->select(['id', 'tenant_id', 'unit_id', 'is_active', 'deleted_at']);
                },
                'invoice.lease.tenant:id,name',
                'invoice.lease.unit:id,unit_number,building_id',
                'invoice.lease.unit.building:id,name',
            ])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function (Payment $payment) {
                $lease = $payment->invoice?->lease;
                $payment->setAttribute('lease_state', match (true) {
                    $lease === null => 'unknown',
                    $lease->deleted_at !== null => 'soft_deleted',
                    (bool) $lease->is_active === false => 'ended',
                    default => 'active',
                });

                return $payment;
            });

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

        // PERF-P7: bulk-compute KYC completion for the entire tenant set in a
        // small fixed number of queries instead of 3N (one lease lookup, one
        // requirements query, and one approved-count query per tenant via
        // User::hasCompletedKyc()).
        //
        // Step 1: pull (tenant_id, landlord_id, building_id) tuples — these
        // determine which requirements apply to which tenant.
        $tenantContext = \DB::table('leases')
            ->join('units', 'leases.unit_id', '=', 'units.id')
            ->whereIn('leases.id', $leaseIds)
            ->where('leases.is_active', true)
            ->select('leases.tenant_id', 'leases.landlord_id', 'units.building_id')
            ->get()
            ->keyBy('tenant_id');

        $total = $tenantContext->count();
        if ($total === 0) {
            return ['total' => 0, 'complete' => 0, 'incomplete' => 0, 'rate' => 0];
        }

        $landlordIds = $tenantContext->pluck('landlord_id')->unique();

        // Step 2: required, active requirements for the landlord(s) in scope.
        // landlord_id null = platform default; building_id null = applies to all.
        $requirements = \DB::table('kyc_requirements')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('is_required', true)
            ->where(function ($q) use ($landlordIds) {
                $q->whereIn('landlord_id', $landlordIds)->orWhereNull('landlord_id');
            })
            ->select('id', 'landlord_id', 'building_id')
            ->get();

        // Step 3: approved submissions grouped by user_id.
        $approved = \DB::table('tenant_kyc_submissions')
            ->whereIn('user_id', $tenantContext->keys())
            ->where('status', 'approved')
            ->select('user_id', 'requirement_id')
            ->get()
            ->groupBy('user_id');

        // Step 4: in-memory completion check per tenant.
        $complete = 0;
        foreach ($tenantContext as $tenantId => $ctx) {
            $applicable = $requirements->filter(function ($req) use ($ctx) {
                $landlordOk = $req->landlord_id === null || $req->landlord_id === $ctx->landlord_id;
                $buildingOk = $req->building_id === null || $req->building_id === $ctx->building_id;

                return $landlordOk && $buildingOk;
            });

            if ($applicable->isEmpty()) {
                $complete++;

                continue;
            }

            $approvedRequirementIds = ($approved[$tenantId] ?? collect())->pluck('requirement_id')->unique();
            if ($approvedRequirementIds->intersect($applicable->pluck('id'))->count() >= $applicable->count()) {
                $complete++;
            }
        }

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
            'arrears_aging' => $this->getArrearsAgingBucketsForLeases($leaseIds),
        ];
    }
}
