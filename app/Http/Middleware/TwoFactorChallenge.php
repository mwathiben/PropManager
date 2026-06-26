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

        $challengeRedirect = $this->challengeRedirectIfNeeded($request);
        if ($challengeRedirect !== null) {
            return $challengeRedirect;
        }

        $setupRedirect = $this->setupRedirectIfNeeded($request);
        if ($setupRedirect !== null) {
            return $setupRedirect;
        }

        return $next($request);
    }

    private function challengeRedirectIfNeeded(Request $request): ?Response
    {
        $user = $request->user();

        if (! $this->twoFactorService->isEnabled($user)) {
            return null;
        }

        if (session('two_factor_verified', false)) {
            return null;
        }

        session(['url.intended' => $request->url()]);

        return redirect()->route('two-factor.challenge');
    }

    private function setupRedirectIfNeeded(Request $request): ?Response
    {
        $user = $request->user();

        if (! $this->twoFactorService->needsSetup($user)) {
            return null;
        }

        if ($request->routeIs('two-factor.*') || $request->routeIs('logout')) {
            return null;
        }

        return redirect()->route('two-factor.index')
            ->with('warning', 'You must enable two-factor authentication to continue.');
    }
}
