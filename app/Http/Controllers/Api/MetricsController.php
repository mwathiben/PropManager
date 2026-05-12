<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Phase-14 OBSERV-1: Prometheus-exposition endpoint for MetricsService
 * counters. Pre-fix, MetricsService::snapshot existed but nothing
 * exposed it over HTTP — Prometheus could not pull. The endpoint is
 * gated by two opt-in mechanisms so it can be public-scrapable
 * (CIDR allowlist) OR private-network-only (bearer token):
 *
 *   - METRICS_BEARER  — if set, requires `Authorization: Bearer X`
 *   - METRICS_ALLOW_IPS — comma-separated CIDRs; if set, only those
 *                         IPs may scrape
 *
 * If NEITHER is set the endpoint returns 503 so an
 * unconfigured production environment can't accidentally publish
 * internal counter data.
 */
class MetricsController extends Controller
{
    public function index(Request $request, MetricsService $metrics): Response
    {
        $bearer = (string) config('observability.metrics.bearer', '');
        $allowList = (string) config('observability.metrics.allow_ips', '');

        if ($bearer === '' && $allowList === '') {
            return response('# /metrics is not configured (set METRICS_BEARER or METRICS_ALLOW_IPS)', 503)
                ->header('Content-Type', 'text/plain; version=0.0.4');
        }

        if ($bearer !== '') {
            $supplied = (string) $request->bearerToken();
            if (! hash_equals($bearer, $supplied)) {
                abort(401, 'invalid bearer');
            }
        }

        if ($allowList !== '') {
            $ip = (string) $request->ip();
            $allowed = false;
            foreach (explode(',', $allowList) as $cidr) {
                if ($this->ipInCidr($ip, trim($cidr))) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                abort(403, 'caller IP not on METRICS_ALLOW_IPS');
            }
        }

        return response($metrics->exportPrometheus(), 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if ($cidr === '') {
            return false;
        }
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $bits = (int) $bits;
        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
