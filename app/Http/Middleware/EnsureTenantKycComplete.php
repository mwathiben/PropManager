<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantKycComplete
{
    /**
     * Handle an incoming request.
     * Redirect tenants with incomplete KYC to the profile completion page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only apply to authenticated tenants
        if ($user && $user->isTenant() && ! $user->hasCompletedKyc()) {
            // Allow access to KYC routes, logout, and API notification routes
            if ($request->routeIs('tenant.kyc.*')
                || $request->routeIs('logout')
                || $request->routeIs('tenant.notifications.api')
            ) {
                return $next($request);
            }

            // For Inertia requests, redirect
            if ($request->header('X-Inertia')) {
                return redirect()->route('tenant.kyc.show')
                    ->with('warning', 'Please complete your profile to continue.');
            }

            // For regular requests
            return redirect()->route('tenant.kyc.show')
                ->with('warning', 'Please complete your profile to continue.');
        }

        return $next($request);
    }
}
