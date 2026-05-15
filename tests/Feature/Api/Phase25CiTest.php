<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Dedoc\Scramble\Generator;
use Tests\TestCase;

/**
 * Phase-25 API-CI-1 watchdog: the routes-vs-spec drift guard.
 *
 * A future contributor:
 *   - Adds /api/v1/landlord/widgets without a JsonResource — Scramble
 *     cannot document the response shape, so the spec misses the
 *     endpoint.
 *   - Removes /api/v1/landlord/buildings/{id}/units but leaves the
 *     entry in the spec — consumers 404.
 *
 * The watchdog catches both. Allow-list: known-partial endpoints
 * (auth/*, webhooks/* — inbound provider-pushed routes,
 * health/metrics) are exempt from the "every route in spec" check
 * — they pre-date Phase 25 and don't have JsonResource returns yet.
 * Adding to the allow-list requires a PR justification.
 */
class Phase25CiTest extends TestCase
{
    /**
     * Path prefixes that are exempt from the "in spec" requirement.
     * Auth + webhook + health + metrics endpoints either don't return
     * JsonResource shapes (auth returns ad-hoc JSON; webhooks return
     * provider-specific bodies) or don't belong in a published API
     * spec (health, metrics — internal observability).
     */
    private const ALLOW_LIST_PREFIXES = [
        'api/health',
        'api/metrics',
        'api/v1/csp-reports',
        'api/v1/auth',
        'api/v1/webhooks',
        'api/v1/integrations',     // Reports endpoints — alias paths reusing ReportController v1; primary coverage at /v1/landlord/reports
        'api/v1/mpesa',            // M-Pesa initiate/status — provider-specific shapes, documented in docs/api/webhook-events.md
        'api/v1/tenant/payments/intasend',  // IntaSend-specific provider shape
        'api/v1/tenant/payments/mpesa',     // M-Pesa STK init/status
        'api/v1/tenant/payments/paystack',  // Paystack-specific provider shape
        'api/v1/landlord/units',   // Phase-25 baseline gap — TODO: JsonResource for unit status update payload
        'api/v2',                  // v2 introduced incrementally; allow-list while v2 endpoints land
        'api/mpesa',
        'api/webhooks',
        'api/banks',               // Bank verification (PaymentsHubController) — landlord-only, not consumer-facing API
        'api/verify-account',
    ];

    public function test_scramble_can_generate_a_complete_spec(): void
    {
        $generator = app(Generator::class);
        $spec = $generator();

        $this->assertIsArray($spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertGreaterThan(
            0,
            count($spec['paths']),
            'API-CI-1: Scramble must produce at least one path.',
        );
    }

    public function test_every_non_allow_listed_api_route_is_in_the_spec(): void
    {
        $generator = app(Generator::class);
        $spec = $generator();
        $documented = array_keys($spec['paths']);

        $missing = [];
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            if ($this->isAllowListed($uri)) {
                continue;
            }
            // Strip leading "api/" prefix to match Scramble's path keys
            // (which omit the prefix). Result must have a single
            // leading slash, never a double slash.
            $specPath = '/'.preg_replace('#^api/#', '', $uri);
            // Scramble keys are template-form (e.g. /v1/foo/{id}).
            if (! in_array($specPath, $documented, true)) {
                $missing[] = $route->methods()[0].' '.$uri;
            }
        }

        $this->assertEmpty(
            $missing,
            'API-CI-1: every API route must appear in the OpenAPI spec (allow-list exemptions: '.implode(', ', self::ALLOW_LIST_PREFIXES)."). Missing:\n - ".implode("\n - ", $missing),
        );
    }

    public function test_every_spec_endpoint_resolves_to_a_real_route(): void
    {
        $generator = app(Generator::class);
        $spec = $generator();

        $registeredUris = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($u) => str_starts_with($u, 'api/'))
            ->all();

        $orphans = [];
        foreach (array_keys($spec['paths']) as $path) {
            $registeredPath = 'api'.$path;  // path is /v1/.. → api/v1/..
            // Scramble emits e.g. /v1/foo/{id}; routes register as api/v1/foo/{id}.
            // Need to match parameter names too — collapse {.*} to .* for fuzz match.
            $pattern = '#^'.preg_quote($registeredPath, '#').'$#';
            $pattern = preg_replace('#\\\\{[^}]+\\\\}#', '\\{[^}]+\\}', $pattern);
            $found = false;
            foreach ($registeredUris as $uri) {
                if (preg_match($pattern, $uri)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $orphans[] = $path;
            }
        }

        $this->assertEmpty(
            $orphans,
            "API-CI-1: every spec endpoint must resolve to a registered route. Orphans:\n - ".implode("\n - ", $orphans),
        );
    }

    private function isAllowListed(string $uri): bool
    {
        foreach (self::ALLOW_LIST_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
