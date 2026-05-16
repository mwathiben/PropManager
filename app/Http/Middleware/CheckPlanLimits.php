<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Only landlords have subscription limits
        if ($user->role !== 'landlord') {
            return $next($request);
        }

        // Super admins bypass all limits
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // PERF-R1: eager-load subscription.plan once before the multiple
        // canAccessFeature/withinLimit/getLimit accesses below. Combined
        // with the in-model memoization on getPlanAttribute, the plan
        // resolves to one query at most per request.
        $user->loadMissing('subscription.plan');

        // Check feature access
        if (! $user->canAccessFeature($feature)) {
            $message = $this->getFeatureUpgradeMessage($feature);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'error' => $message,
                    'upgrade_url' => route('subscription.plans'),
                    'feature' => $feature,
                ], 403);
            }

            return redirect()->route('subscription.plans')
                ->with('error', $message);
        }

        // Check quantity limits for creation routes (POST requests)
        if ($request->isMethod('POST')) {
            $limitFeature = $this->getLimitFeature($feature);

            if ($limitFeature && ! $user->withinLimit($limitFeature)) {
                $limit = $user->getLimit($limitFeature);
                $message = "You've reached your plan limit of {$limit} {$limitFeature}. Please upgrade your plan for more capacity.";

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'error' => $message,
                        'upgrade_url' => route('subscription.plans'),
                        'feature' => $limitFeature,
                        'limit' => $limit,
                        'current' => $user->getCurrentUsage($limitFeature),
                    ], 403);
                }

                return redirect()->back()
                    ->with('error', $message);
            }
        }

        return $next($request);
    }

    /**
     * Phase-35 PLATFORM-METER-1: record successful WRITE-class usage
     * AFTER the response is sent. We only count POST/PUT/PATCH/
     * DELETE since reads aren't billable. terminate() fires after
     * the response is dispatched so the recording I/O doesn't add
     * to user-perceived latency.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $user = $request->user();
            if (! $user || $user->role !== 'landlord' || $user->isSuperAdmin()) {
                return;
            }
            if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return;
            }
            if ($response->getStatusCode() >= 400) {
                return;
            }

            $route = $request->route();
            // Pull the feature name from the middleware parameter on the
            // matched route — Laravel renders `plan:units` as parameters
            // ['units']. Falls back to null if the resolver can't find one.
            $feature = $this->resolveFeatureFromRoute($route);
            if ($feature === null) {
                return;
            }

            app(\App\Services\Platform\MeteredUsageRecorder::class)
                ->record($user->id, $feature, 1);
        } catch (\Throwable) {
            // Fail-open: metering MUST NEVER bubble out of terminate.
        }
    }

    private function resolveFeatureFromRoute($route): ?string
    {
        if (! $route) {
            return null;
        }
        foreach ($route->middleware() as $entry) {
            if (str_starts_with($entry, 'plan:')) {
                $feature = substr($entry, 5);

                return $feature !== '' ? $feature : null;
            }
        }

        return null;
    }

    /**
     * Map feature to limit feature name.
     */
    protected function getLimitFeature(string $feature): ?string
    {
        return match ($feature) {
            'properties' => 'properties',
            'buildings' => 'buildings',
            'units' => 'units',
            'caretakers' => 'caretakers',
            default => null,
        };
    }

    /**
     * Get upgrade message for a feature.
     */
    protected function getFeatureUpgradeMessage(string $feature): string
    {
        return match ($feature) {
            'water_billing' => 'Water billing requires a Basic plan or higher. Please upgrade to access this feature.',
            'ocr' => 'OCR meter reading requires a Pro plan or higher. Please upgrade to access this feature.',
            'reports' => 'Reports & analytics requires a Basic plan or higher. Please upgrade to access this feature.',
            'bulk_operations' => 'Bulk operations requires a Pro plan or higher. Please upgrade to access this feature.',
            'documents' => 'Document storage requires a Basic plan or higher. Please upgrade to access this feature.',
            'sms' => 'SMS notifications requires a Pro plan or higher. Please upgrade to access this feature.',
            default => 'This feature requires a higher plan. Please upgrade to access it.',
        };
    }
}
