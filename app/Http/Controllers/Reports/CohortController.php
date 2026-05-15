<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CohortService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-27 BI-COHORT-1/2/3: tenant cohort analytics surface.
 *
 * The controller is thin — every analytic lives in CohortService so
 * the API endpoint (Api\ReportController, future) and scheduled-email
 * generator can share the same code path. Authorization runs through
 * the existing role:landlord middleware on the route + the
 * AuthServiceProvider Gate::before (super-admin bypass, DPA-4
 * restriction check) — no new policy required because the data is
 * already TenantScoped via Lease + Payment models.
 */
class CohortController extends Controller
{
    public function __construct(private CohortService $cohorts) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->role === 'landlord' ? $user->id : (int) $user->landlord_id;

        $lookback = (int) $request->query('lookback', 12);
        $lookback = max(1, min(36, $lookback));

        $cohortMonth = (string) $request->query('cohort', now()->subMonths($lookback - 1)->format('Y-m'));

        return Inertia::render('Reports/Cohort', [
            'retentionMatrix' => $this->cohorts->retentionMatrix($landlordId, $lookback),
            'acquisitionTable' => $this->cohorts->acquisitionTable($landlordId, $lookback),
            'lifetimeValue' => $this->cohorts->lifetimeValue($landlordId, $cohortMonth),
            'lookback' => $lookback,
            'cohortMonth' => $cohortMonth,
        ]);
    }
}
