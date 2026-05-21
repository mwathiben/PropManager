<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Water\WaterModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-79 WATER-GATE-3: backend gate for the conditional water module. Hiding
 * the nav is not enough — a landlord (or their caretaker/tenant) whose water
 * module is disabled must not reach any water URL by typing it. Mirrors the
 * featureAccess.water_billing share so UI + backend agree.
 */
class EnsureWaterModule
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! WaterModuleAccess::enabledFor(auth()->user())) {
            if ($request->expectsJson()) {
                abort(403, 'Water billing is not enabled.');
            }

            return redirect()->route('dashboard')
                ->with('error', __('water.module_disabled'));
        }

        return $next($request);
    }
}
