<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-KYC completion aggregation, extracted from DashboardService (M2
 * decomposition step 2). Bulk-computes KYC completion for a tenant set in
 * a small fixed number of queries (PERF-P7). Behaviour is locked by
 * tests/Feature/Services/DashboardKycStatsTest.php — a verbatim move.
 *
 * @phpstan-type KycStats array{total: int, complete: int, incomplete: int, rate: int|float}
 */
class KycStatsCalculator
{
    /**
     * @return array{total: int, complete: int, incomplete: int, rate: int|float}
     */
    public function forLeases(Collection $leaseIds): array
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
        $tenantContext = DB::table('leases')
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
        $requirements = DB::table('kyc_requirements')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('is_required', true)
            ->where(function ($q) use ($landlordIds) {
                $q->whereIn('landlord_id', $landlordIds)->orWhereNull('landlord_id');
            })
            ->select('id', 'landlord_id', 'building_id')
            ->get();

        // Step 3: approved submissions grouped by user_id.
        $approved = DB::table('tenant_kyc_submissions')
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
}
