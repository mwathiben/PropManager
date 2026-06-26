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

        if (! $this->needsKycCheck($user)) {
            return $next($request);
        }

        if ($this->isKycExemptRoute($request)) {
            return $next($request);
        }

        return redirect()->route('tenant.kyc.show')
            ->with('warning', 'Please complete your profile to continue.');
    }

    private function needsKycCheck(mixed $user): bool
    {
        return $user && $user->isTenant() && ! $user->hasCompletedKyc();
    }

    private function isKycExemptRoute(Request $request): bool
    {
        return $request->routeIs('tenant.kyc.*')
            || $request->routeIs('logout')
            || $request->routeIs('tenant.notifications.api');
    }
}
