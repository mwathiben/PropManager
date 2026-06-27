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

        if (! $user || ! $user->isScopeOwner()) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        if (! $user->hasCompletedOnboarding()) {
            return $this->handleIncompleteOnboarding($user, $next, $request);
        }

        return $next($request);
    }

    /**
     * Determine whether the request targets a route exempt from the onboarding check.
     */
    private function isAllowedRoute(Request $request): bool
    {
        foreach ($this->allowedRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a scope owner whose onboarding is not yet marked complete.
     *
     * Legacy users who already have a property are auto-completed and allowed through;
     * everyone else is redirected to the onboarding flow.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    private function handleIncompleteOnboarding(mixed $user, Closure $next, Request $request): Response
    {
        if ($user->properties()->exists()) {
            $progress = $user->getOrCreateOnboardingProgress();
            $progress->markComplete();

            return $next($request);
        }

        return redirect()->route('onboarding.index');
    }
}
