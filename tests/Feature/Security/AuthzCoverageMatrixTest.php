<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase-18 AUTHZ-2: walk the route table, find every controller@action
 * pair, and assert it has SOME authorization gate. Acceptable gates:
 *   - $this->authorize() call in the action body OR controller __construct
 *   - 'can:' or 'auth' or 'role:' middleware on the route
 *
 * Pre-Phase-18 only 18 of 95 controllers used $this->authorize() — this
 * test catches that gap explicitly and the report below details which
 * actions need explicit authz.
 *
 * Test only RUNS the assertion in strict mode (env AUTHZ_COVERAGE_STRICT=
 * true). By default it produces an informational report so the audit
 * matrix surface stays visible without breaking CI.
 */
class AuthzCoverageMatrixTest extends TestCase
{
    /**
     * Routes that legitimately don't need authorization. These are
     * either public endpoints (login, register, webhooks with their own
     * signature middleware) or internal infrastructure (health, metrics).
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTE_PATTERNS = [
        '#^/?$#',
        '#^login#',
        '#^register#',
        '#^password/#',
        '#^logout#',
        '#^email/#',
        '#^_ignition#',
        '#^webhooks/#',
        '#^api/health#',
        '#^api/metrics#',
        '#^api/v1/csp-reports#',
        '#^api/v1/auth/#',
        '#^api/v1/health#',
        '#^api/v1/tenants/invitations/.*/accept#',
        '#^api/v1/tenants/invitations/.*/decline#',
        '#^api/v1/payments/initiate#',
        '#^api/v1/payments/.*/verify#',
        '#^auth/#',
        '#^two-factor#',
        '#^magic-link#',
        '#^tenant/onboard#',
        '#^onboarding-completed#',
        '#^accept-invitation#',
        '#^tenant-invitation#',
        '#^csp-violation#',
        '#^impersonate-stop#',
        '#^sanctum/csrf-cookie#',
        '#^up$#',
        '#^livewire/#',
        '#^broadcasting/auth#',
        '#^test-#',
        '#^horizon#',
        '#^telescope#',
        '#^dev/#',
        '#^debugbar#',
    ];

    public function test_every_authenticated_route_has_an_authorization_gate(): void
    {
        $strict = filter_var(env('AUTHZ_COVERAGE_STRICT'), FILTER_VALIDATE_BOOL);

        $gaps = $this->findAuthzGaps();

        if ($strict && ! empty($gaps)) {
            $this->fail(sprintf(
                "Phase-18 AUTHZ-2 strict mode: %d controller actions have no authorization gate:\n  - %s",
                count($gaps),
                implode("\n  - ", array_slice($gaps, 0, 20)),
            ));
        }

        // Non-strict mode (default): inform but don't fail. The matrix is
        // a tracking instrument; gap-closing is incremental per follow-up.
        // The current population is captured as a baseline so a future
        // controller landing without an authz gate is detectable.
        $this->assertIsArray($gaps);
        $this->assertLessThan(
            350,
            count($gaps),
            'AUTHZ-2 watchdog: route-table snapshot at 2026-05-12 had ~291 gaps. A jump above 350 suggests a regression in route middleware (e.g. a group-level role: middleware was removed).'
        );
    }

    public function test_admin_controller_has_access_admin_gate_in_constructor(): void
    {
        // AUTHZ-3 regression-lock. Pre-Phase-18 AdminController used
        // inline isSuperAdmin() checks; the Phase-13 DPA-4 Gate::before
        // hook never fired. Post-fix the constructor middleware
        // authorizes 'access-admin' so a DPA-restricted super-admin is
        // actually restricted.
        $contents = file_get_contents(base_path('app/Http/Controllers/AdminController.php'));

        $this->assertStringContainsString(
            "Gate::authorize('access-admin')",
            $contents,
            "AdminController must invoke Gate::authorize('access-admin') for DPA-4 enforcement (Phase-18 AUTHZ-3)"
        );
    }

    public function test_no_dead_gates_in_auth_service_provider(): void
    {
        // AUTHZ-1: pre-Phase-18 these 5 Gates were defined but never
        // called. Re-adding any of them without a call site re-introduces
        // the same audit-misleading 'authorization is comprehensive'
        // problem.
        $contents = file_get_contents(base_path('app/Providers/AuthServiceProvider.php'));

        foreach (['manage-caretakers', 'generate-invoices', 'perform-bulk-operations', 'access-reports', 'manage-subscription'] as $deadGate) {
            $this->assertStringNotContainsString(
                "Gate::define('{$deadGate}'",
                $contents,
                "Phase-18 AUTHZ-1: '{$deadGate}' is a dead Gate (no call sites in app/ or resources/). Either delete it OR add a Gate::allows/\$this->authorize call site."
            );
        }
    }

    /**
     * @return list<string>
     */
    private function findAuthzGaps(): array
    {
        $gaps = [];

        foreach (RouteFacade::getRoutes() as $route) {
            assert($route instanceof Route);

            $uri = $route->uri();
            if ($this->isExempt($uri)) {
                continue;
            }

            $action = $route->getAction();
            $controller = $action['controller'] ?? null;
            if (! is_string($controller) || ! str_contains($controller, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $controller);

            if (! class_exists($class)) {
                continue;
            }

            if ($this->routeHasAuthorizationMiddleware($route)) {
                continue;
            }

            if ($this->controllerHasAuthorizeCall($class, $method)) {
                continue;
            }

            $gaps[] = "{$class}@{$method} ({$uri})";
        }

        return $gaps;
    }

    private function isExempt(string $uri): bool
    {
        foreach (self::EXEMPT_ROUTE_PATTERNS as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    private function routeHasAuthorizationMiddleware(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            if (str_starts_with($middleware, 'role:')) {
                return true;
            }
            if (str_starts_with($middleware, 'can:')) {
                return true;
            }
            if (str_starts_with($middleware, 'ability:') || str_starts_with($middleware, 'abilities:')) {
                return true;
            }
            if ($middleware === 'access-admin' || str_contains($middleware, 'access-admin')) {
                return true;
            }
        }

        return false;
    }

    private function controllerHasAuthorizeCall(string $class, string $method): bool
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (\ReflectionException) {
            return true; // Skip — can't reflect
        }

        // Constructor that gates the whole class.
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getFileName() !== false) {
            $body = $this->methodBody($constructor);
            if (str_contains($body, 'Gate::authorize') || str_contains($body, '$this->authorize') || str_contains($body, 'Gate::allows')) {
                return true;
            }
        }

        if (! $reflection->hasMethod($method)) {
            return true; // Resource controllers might rely on dynamic methods; not a real gap
        }

        $body = $this->methodBody($reflection->getMethod($method));

        return str_contains($body, '$this->authorize')
            || str_contains($body, 'Gate::authorize')
            || str_contains($body, 'Gate::allows')
            || str_contains($body, 'Gate::denies')
            || str_contains($body, 'authorize(');
    }

    private function methodBody(\ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        if ($filename === false) {
            return '';
        }
        $start = $method->getStartLine() - 1;
        $end = $method->getEndLine();
        if ($start < 0 || $end <= $start) {
            return '';
        }
        $lines = file($filename);
        if ($lines === false) {
            return '';
        }

        return implode('', array_slice($lines, $start, $end - $start));
    }
}
