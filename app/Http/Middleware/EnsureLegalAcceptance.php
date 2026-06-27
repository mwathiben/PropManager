<?php

namespace App\Http\Middleware;

use App\Models\Consent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalAcceptance
{
    /**
     * Routes that should be excluded from the check.
     */
    protected array $except = [
        'consent.*',
        'legal.*',
        'logout',
        'password.*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not authenticated
        if (! $request->user()) {
            return $next($request);
        }

        // Skip if compliance features are disabled
        if (! config('security.compliance.gdpr_enabled', true)) {
            return $next($request);
        }

        // Skip excluded routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if user has all required consents
        $missingConsents = Consent::getMissingConsents($request->user());

        if (! empty($missingConsents)) {
            return $this->missingConsentsResponse($request, $missingConsents);
        }

        return $next($request);
    }

    /**
     * Build the appropriate response when required consents are missing.
     *
     * JSON 403 only for true non-Inertia API/JSON clients.
     * Inertia requests use X-Inertia header — redirect those so the
     * SPA router picks up the consent page correctly (mirrors EnsureTenantKycComplete).
     */
    private function missingConsentsResponse(Request $request, array $missingConsents): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Legal acceptance required',
                'missing_consents' => $missingConsents,
                'redirect_url' => route('consent.required'),
            ], 403);
        }

        return redirect()->route('consent.required')
            ->with('missing_consents', $missingConsents);
    }

    /**
     * Check if the request should skip the legal check.
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
