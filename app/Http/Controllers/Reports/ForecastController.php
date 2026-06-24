<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ForecastService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-27 BI-FORECAST-1/2/3: rent-roll forecast + vacancy projection
 * surface. Authorization: role:landlord middleware + Gate::before.
 */
class ForecastController extends Controller
{
    public function __construct(private ForecastService $forecast) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->effectiveScopeId();

        $months = (int) $request->query('months', 12);
        $months = max(1, min(24, $months));

        return Inertia::render('Reports/Forecast', [
            'rentRoll' => $this->forecast->rentRoll($landlordId, $months),
            'vacancyProjection' => $this->forecast->vacancyProjection($landlordId),
            'months' => $months,
        ]);
    }
}
