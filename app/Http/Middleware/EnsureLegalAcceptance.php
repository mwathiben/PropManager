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
            // For API requests, return JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Legal acceptance required',
                    'missing_consents' => $missingConsents,
                    'redirect_url' => route('consent.required'),
                ], 403);
            }

            // For web requests, redirect to consent page
            return redirect()->route('consent.required')
                ->with('missing_consents', $missingConsents);
        }

        return $next($request);
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
