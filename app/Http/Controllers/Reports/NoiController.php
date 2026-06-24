<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\NoiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-27 BI-NOI-1/2/3: NOI + cap rate analytics surface.
 *
 * Authorization runs through role:landlord middleware + Gate::before
 * (super-admin bypass + DPA-4 restriction). The data is already
 * TenantScoped via Property / Payment / Expense models — no new
 * Policy required.
 */
class NoiController extends Controller
{
    public function __construct(private NoiService $noi) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->effectiveScopeId();

        $window = (string) $request->query('window', '12m');
        [$start, $end] = $this->resolveWindow($window);

        return Inertia::render('Reports/Noi', [
            'byProperty' => $this->noi->byProperty($landlordId, $start, $end),
            'capRate' => $this->noi->capRate($landlordId, $start, $end),
            'window' => $window,
        ]);
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function resolveWindow(string $window): array
    {
        $end = Carbon::now()->endOfDay();
        $start = match ($window) {
            '1m' => $end->copy()->subMonth(),
            '3m' => $end->copy()->subMonths(3),
            '6m' => $end->copy()->subMonths(6),
            '12m' => $end->copy()->subYear(),
            'ytd' => Carbon::now()->startOfYear(),
            default => $end->copy()->subYear(),
        };

        return [$start, $end];
    }
}
