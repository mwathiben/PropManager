<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\LandlordDashboard;
use App\Services\Reports\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-50 LANDLORD-DASHBOARDS-3: landlord-facing dashboard show route.
 *
 * Resolves /dashboards/{slug} by (landlord_id, slug) — the TenantScope
 * trait + the explicit slug filter is two layers of isolation. The
 * controller does NOT use route model binding because dashboard.slug
 * needs to be scoped per-landlord, not globally unique.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboards,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $dashboard = LandlordDashboard::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Dashboards/Show', [
            'payload' => $this->dashboards->buildPayload($dashboard),
        ]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }
}
