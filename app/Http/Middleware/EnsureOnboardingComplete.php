<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * Routes that are allowed even if onboarding is not complete
     */
    protected array $allowedRoutes = [
        'onboarding.*',
        'logout',
        'profile.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Only check for authenticated scope owners (landlords + managers)
        if (! $user || ! $user->isScopeOwner()) {
            return $next($request);
        }

        // Allow access to permitted routes
        foreach ($this->allowedRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // Check if onboarding is complete
        if (! $user->hasCompletedOnboarding()) {
            // Check if they have at least one property (legacy users who registered before onboarding)
            if ($user->properties()->exists()) {
                // Mark as complete for legacy users
                $progress = $user->getOrCreateOnboardingProgress();
                $progress->markComplete();

                return $next($request);
            }

            // Redirect to onboarding
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
