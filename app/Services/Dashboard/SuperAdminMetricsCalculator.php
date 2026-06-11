<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceCacheService;
use App\Traits\DatabaseAgnosticQueries;
use Illuminate\Support\Collection;

/**
 * Platform-wide super-admin metrics, extracted from DashboardService (M2
 * decomposition step 3). Aggregates system health, action items, and the
 * top/recent landlords with their monthly revenue across all tenants
 * (every query bypasses the landlord global scope). Behaviour is locked by
 * tests/Feature/Services/DashboardSuperAdminMetricsTest.php — a verbatim move.
 */
class SuperAdminMetricsCalculator
{
    use DatabaseAgnosticQueries;

    /**
     * @return array{systemHealth: array<string, mixed>, actionItems: array<string, int>, landlords: Collection, topLandlords: Collection}
     */
    public function metrics(): array
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
            $monthlyRevenues = $this->landlordsMonthlyRevenue($landlordIds);
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

    private function landlordsMonthlyRevenue(Collection $landlordIds): Collection
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
}
