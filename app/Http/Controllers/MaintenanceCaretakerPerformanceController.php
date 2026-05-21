<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Services\Maintenance\CaretakerPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-80 CARETAKER-PERF-2: landlord-facing caretaker performance comparison
 * (within-SLA %, avg resolution, avg first-response, open/overdue, water
 * readings recorded, escalations raised) across the landlord's caretakers.
 */
class MaintenanceCaretakerPerformanceController extends Controller
{
    use WithLandlordScope;

    private const WINDOWS = [30, 90, 365];

    public function index(Request $request, CaretakerPerformanceService $performance): Response
    {
        $window = (int) $request->integer('window', 90);
        if (! in_array($window, self::WINDOWS, true)) {
            $window = 90;
        }

        return Inertia::render('Maintenance/CaretakerPerformance', [
            'caretakers' => $performance->forLandlord($this->getLandlordId(), $window),
            'window' => $window,
            'windows' => self::WINDOWS,
        ]);
    }
}
