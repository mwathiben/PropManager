<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Water\UpdateWaterAbstractionLimitRequest;
use App\Models\Building;

/**
 * Phase-92 WATER-COMPLIANCE: landlord-only capture of a borehole building's annual
 * WRA abstraction limit. The permit/certificate files themselves are uploaded via
 * the shared DocumentController (documentable_type=Building), reusing the Phase-82
 * lifecycle + expiry reminders.
 */
class WaterComplianceController extends Controller
{
    public function updateLimit(UpdateWaterAbstractionLimitRequest $request, Building $building)
    {
        $building->update([
            'water_abstraction_limit' => $request->input('water_abstraction_limit'),
        ]);

        return back()->with('success', __('water.compliance.limit_saved'));
    }
}
