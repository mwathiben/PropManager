<?php

declare(strict_types=1);

namespace App\Http\Controllers\Insight;

use App\Http\Controllers\Controller;
use App\Services\Insight\InsightDashboardService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-36 INSIGHT-OPS-2: super-admin operator dashboard.
 *
 * Composes the top-of-funnel KPIs (MRR, churn, incidents, alerts,
 * dependency health) into a single Inertia page so the operator
 * doesn't have to navigate to 5+ different routes to assemble a
 * full picture.
 */
class OpsDashboardController extends Controller
{
    public function __construct(
        private readonly InsightDashboardService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Ops/Index', [
            'summary' => $this->service->operatorSummary(),
        ]);
    }
}
