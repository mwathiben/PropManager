<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\SampleDataSeederService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-31 ONB-SAMPLE-2: landlord-facing toggle for the prospect demo
 * dataset. Both endpoints are idempotent — populate refuses if a real
 * lease exists, reset is a no-op if no populated run exists.
 */
class SampleDataController extends Controller
{
    public function populate(Request $request, SampleDataSeederService $seeder): RedirectResponse
    {
        $run = $seeder->populate($request->user());
        if ($run === null) {
            return Redirect::back()->withErrors(['sample' => __('onboarding.sample.refused_real_data')]);
        }

        return Redirect::back()->with('success', __('onboarding.sample.populated_success'));
    }

    public function reset(Request $request, SampleDataSeederService $seeder): RedirectResponse
    {
        $count = $seeder->reset($request->user());

        return Redirect::back()->with('success', __('onboarding.sample.reset_success', ['count' => $count]));
    }
}
