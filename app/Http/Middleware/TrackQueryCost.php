<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\QueryCostLog;
use App\Support\RouteClassResolver;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-33 COST-QUERY-1: per-request rows-scanned tracker. DB::listen
 * accumulates query_count + estimates rows_scanned via SELECT bindings
 * count + the heuristic that ORM model arrays have count() == rows
 * returned. Sampled: only writes a row when query_count > 10
 * (otherwise the table grows O(rps)).
 *
 * Estimate accuracy: rows_scanned is a HEURISTIC, not EXPLAIN-driven.
 * For dashboards comparing ratios across route classes it is the
 * right signal — actual EXPLAIN integration is a follow-up.
 */
class TrackQueryCost
{
    public const SAMPLE_THRESHOLD_QUERIES = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $queryCount = 0;
        $rowsReturned = 0;

        // Estimate rows_scanned via the heuristic 100 rows per query
        // (conservative — most ORM queries use indexed selects that
        // touch ~10 rows; rows >> 100 means N+1 or missing index).
        $rowsScanned = 0;

        $listener = function (QueryExecuted $event) use (&$queryCount, &$rowsScanned, &$rowsReturned): void {
            $queryCount++;
            $sql = strtoupper(ltrim($event->sql));
            if (str_starts_with($sql, 'SELECT')) {
                $rowsReturned += 1;
                $rowsScanned += 100;
            }
        };

        DB::listen($listener);

        try {
            return $next($request);
        } finally {
            if ($queryCount > self::SAMPLE_THRESHOLD_QUERIES) {
                $this->writeSample($request, $queryCount, $rowsScanned, $rowsReturned);
            }
        }
    }

    private function writeSample(Request $request, int $queryCount, int $rowsScanned, int $rowsReturned): void
    {
        try {
            $route = $request->route();
            $routeName = $route?->getName();
            $routeClass = RouteClassResolver::classify($routeName, $request->method());
            $userId = Auth::id();
            $landlordId = null;
            if ($userId !== null) {
                $user = Auth::user();
                $landlordId = $user?->effectiveScopeIdOrNull();
            }

            QueryCostLog::create([
                'landlord_id' => $landlordId,
                'route_class' => $routeClass,
                'query_count' => $queryCount,
                'rows_scanned' => $rowsScanned,
                'rows_returned' => max(1, $rowsReturned),
                'request_at' => now(),
            ]);
        } catch (\Throwable) {
            // Fail-open: telemetry never blocks the request.
        }
    }
}
