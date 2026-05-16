<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-28 TENANT-CI-1 — every tenant route must carry role:tenant
 * middleware. Mirrors the Phase-20 AuthzCoverageMatrixTest pattern.
 *
 * Whitelisted exceptions are the pre-KYC + pre-payment-verification
 * routes (the gating middleware itself sits later in the chain, so
 * these need to be reachable from a fresh login).
 */
class Phase28TenantSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route URIs that intentionally diverge from the standard
     * /tenant/* role:tenant chain. Currently EMPTY — every existing
     * URI under /tenant/ carries role:tenant (the pre-payment and
     * pre-kyc routes use that middleware first, with the additional
     * gates conditionally added later in the chain). If a future
     * route legitimately needs the exemption, add the URI here with
     * an inline justification.
     *
     * @var string[]
     */
    private const URI_WHITELIST = [];

    public function test_every_tenant_uri_carries_role_tenant_middleware(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // Only inspect routes under the /tenant/ prefix (the
            // documented portal surface). Auth-shape routes elsewhere
            // are governed by their own watchdogs.
            if (! str_starts_with($uri, 'tenant/')) {
                continue;
            }
            if (in_array($uri, self::URI_WHITELIST, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $hasRoleTenant = collect($middleware)
                ->contains(fn ($m) => str_starts_with((string) $m, 'role:tenant'));

            if (! $hasRoleTenant) {
                $offenders[] = $route->methods()[0].' /'.$uri.' (middleware: '.implode(',', $middleware).')';
            }
        }

        $this->assertEmpty(
            $offenders,
            "The following /tenant/* routes are missing role:tenant middleware:\n  - ".
                implode("\n  - ", $offenders).
                "\n\nEither add role:tenant or add the URI to URI_WHITELIST with a justifying inline comment.",
        );
    }

    public function test_whitelist_does_not_contain_unknown_uris(): void
    {
        // An empty whitelist is the desired baseline — assert that and
        // walk any future entries to catch stale URIs.
        if (self::URI_WHITELIST === []) {
            $this->assertSame([], self::URI_WHITELIST);

            return;
        }

        $known = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        foreach (self::URI_WHITELIST as $uri) {
            $this->assertContains(
                $uri,
                $known,
                "URI_WHITELIST contains '{$uri}' but no such route is registered — drop the stale entry.",
            );
        }
    }
}
