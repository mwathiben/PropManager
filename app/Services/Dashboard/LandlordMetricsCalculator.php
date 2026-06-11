<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Building;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Building/landlord dashboard metrics, extracted from DashboardService (M2
 * decomposition step 5 — the largest single method, ~170 lines). Computes
 * the action items, financial metrics, occupancy stats, and recent
 * activity for the active building scope (or cross-building when in
 * all-buildings mode). Behaviour is locked by the dashboard feature suite
 * (DashboardControllerTest + the building-dashboard tests), which exercise
 * this via the landlord render path — a verbatim move.
 */
class LandlordMetricsCalculator
{
    public function __construct(
        private ArrearsAgingCalculator $arrears = new ArrearsAgingCalculator,
        private KycStatsCalculator $kyc = new KycStatsCalculator,
    ) {}

    /**
     * @param  Collection<int, \App\Models\Unit>  $allUnits
     * @param  Collection<int, Building>  $wings
     * @param  Collection<int, \App\Models\Unit>|null  $crossBuildingUnits
     * @param  array<int, int>  $crossBuildingIds
     * @return array{actionItems: array<string, mixed>, financialMetrics: array<string, mixed>, arrearsAging: array<string, float>, stats: array<string, mixed>, recentPayments: Collection, recentTickets: Collection, expiringLeases: Collection, tenantKycStats: array<string, mixed>}
     */
    public function calculate(
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
            'expiring_documents' => Document::query()
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

        $arrearsAging = $this->arrears->agingBucketsForLeases($metricsLeaseIds);

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

        $tenantKycStats = $this->kyc->forLeases($metricsLeaseIds);

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
}
