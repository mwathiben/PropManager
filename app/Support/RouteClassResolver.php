<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phase-22 PERF-SLO-2: maps a route to its SLO route class. One shared
 * definition so the slo:report command (PERF-SLO-3), the SLO budgets in
 * config/observability.php, and the docs/runbooks/slo.md taxonomy all
 * agree on what "read_path" vs "write_path" means.
 *
 * Classification is name-first, method-second:
 *   webhook    — webhook ingress (route name contains "webhook")
 *   report     — reports / exports (name starts "reports." or contains
 *                "report"/"export")
 *   write_path — mutations: name ends .store/.update/.destroy, OR the
 *                HTTP method is a non-idempotent verb
 *   read_path  — everything else (the common navigation case)
 */
final class RouteClassResolver
{
    public const CLASSES = ['read_path', 'write_path', 'webhook', 'report'];

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public static function classify(?string $routeName, string $method = 'GET'): string
    {
        $name = strtolower($routeName ?? '');
        $method = strtoupper($method);

        if ($name !== '' && str_contains($name, 'webhook')) {
            return 'webhook';
        }

        if ($name !== '' && (str_starts_with($name, 'reports.') || str_contains($name, 'report') || str_contains($name, 'export'))) {
            return 'report';
        }

        if ($name !== '' && (str_ends_with($name, '.store') || str_ends_with($name, '.update') || str_ends_with($name, '.destroy'))) {
            return 'write_path';
        }

        if (in_array($method, self::WRITE_METHODS, true)) {
            return 'write_path';
        }

        return 'read_path';
    }

    /**
     * The configured p95 latency budget (ms) for a route class, or null
     * if the class has no budget defined.
     */
    public static function budgetMsFor(string $routeClass): ?int
    {
        $budgets = config('observability.slo.latency_budgets_ms', []);

        return isset($budgets[$routeClass]) ? (int) $budgets[$routeClass] : null;
    }
}
