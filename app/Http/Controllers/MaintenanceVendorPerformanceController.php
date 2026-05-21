<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Services\Vendors\VendorPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-75 VENDOR-PERF-2: landlord-facing vendor performance comparison
 * (within-SLA %, avg resolution, open overdue, cost per ticket) across all of
 * the landlord's active vendors.
 */
class MaintenanceVendorPerformanceController extends Controller
{
    use WithLandlordScope;

    private const WINDOWS = [30, 90, 365];

    public function index(Request $request, VendorPerformanceService $performance): Response
    {
        $window = (int) $request->integer('window', 90);
        if (! in_array($window, self::WINDOWS, true)) {
            $window = 90;
        }

        return Inertia::render('Maintenance/VendorPerformance', [
            'vendors' => $performance->forLandlord($this->getLandlordId(), $window),
            'window' => $window,
            'windows' => self::WINDOWS,
        ]);
    }
}
