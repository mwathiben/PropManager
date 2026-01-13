<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorChallenge
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Check if 2FA is enabled and user needs to verify
        if ($this->twoFactorService->isEnabled($user)) {
            // Check if the user has passed 2FA verification this session
            if (! session('two_factor_verified', false)) {
                // Store intended URL and redirect to 2FA challenge
                session(['url.intended' => $request->url()]);

                return redirect()->route('two-factor.challenge');
            }
        }

        // Check if 2FA setup is required but not completed
        if ($this->twoFactorService->needsSetup($user)) {
            // Allow access to 2FA setup routes
            if ($request->routeIs('two-factor.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('two-factor.index')
                ->with('warning', 'You must enable two-factor authentication to continue.');
        }

        return $next($request);
    }
}
