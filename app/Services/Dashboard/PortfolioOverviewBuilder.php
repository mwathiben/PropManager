<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Property\PropertyMetricsService;

/**
 * Landlord portfolio overview, extracted from DashboardService (M2
 * decomposition step 4). Builds the cross-property landing payload
 * (portfolio KPIs + per-property cards + landlord-wide action counts).
 * Behaviour is locked by tests/Feature/Dashboard/Phase105PortfolioHomeTest.php
 * — this was a verbatim move, so that existing suite proves parity.
 */
class PortfolioOverviewBuilder
{
    /**
     * Phase-105 PORTFOLIO-HOME: the landlord's landing — a cross-property overview
     * (portfolio KPIs + per-property cards that drill into a building dashboard) instead of
     * defaulting straight into one building. Reuses PropertyMetricsService::forLandlord (no
     * N+1) + landlord-scoped action counts. Building-scoped detail stays in
     * getLandlordDashboardData (rendered when a building_id is chosen).
     *
     * @return array{redirect?: string, kpis?: array<string, mixed>, actions?: array<string, mixed>, properties?: array<int, array<string, mixed>>}
     */
    public function forLandlord(User $landlord): array
    {
        $landlordId = (int) $landlord->id;

        $rows = app(PropertyMetricsService::class)->forLandlord($landlordId);
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
            'actions' => $this->actionSummary($landlordId),
            'properties' => $properties,
        ];
    }

    /**
     * Landlord-wide action counts for the portfolio header (explicitly landlord-scoped).
     *
     * @return array{overdue_invoices: int, overdue_amount: float, open_tickets: int, expiring_leases: int}
     */
    private function actionSummary(int $landlordId): array
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
}
