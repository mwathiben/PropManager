<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\Growth\CohortRetentionService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-66 COHORT-RETENTION-2: super-admin cohort-retention dashboard.
 * Route-gated to role:super_admin; the service re-checks super-admin
 * before the cross-tenant read (defence in depth).
 */
class OpsCohortRetentionController extends Controller
{
    public function index(CohortRetentionService $cohorts): Response
    {
        $comparison = $cohorts->sourceComparison(12);

        return Inertia::render('Ops/Growth/CohortRetention', [
            'source_comparison' => $comparison['sources'],
            'baseline' => $comparison['baseline'],
            'month_range' => $comparison['month_range'],
            'min_sample' => $comparison['min_sample'],
        ]);
    }
}
