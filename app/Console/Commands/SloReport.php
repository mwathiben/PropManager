<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Support\RouteClassResolver;
use Illuminate\Console\Command;

/**
 * Phase-22 PERF-SLO-3: aggregate the http_request_ms histogram emitted
 * by the RecordRequestLatency middleware (PERF-SLO-1) and report
 * p50/p95/p99 latency per route + per route class, comparing each class
 * against its configured SLO budget (PERF-SLO-2,
 * config/observability.php).
 *
 * Slow-query:report (Phase-21) does the same for DB query shapes; this
 * is its HTTP-latency sibling — the question it answers is "is route
 * class X within its p95 budget".
 *
 * Usage:
 *   php artisan slo:report
 *   php artisan slo:report --since=7 --json
 *   php artisan slo:report --route-class=write_path
 *   php artisan slo:report --fail-on-breach   # exit 1 if any class is out of SLO
 */
class SloReport extends Command
{
    protected $signature = 'slo:report '
        .'{--since= : Number of metric day-buckets to aggregate (default: config slo.evaluation_window_days)} '
        .'{--route-class= : Filter to a single route class (read_path|write_path|webhook|report)} '
        .'{--json : Emit JSON instead of a table} '
        .'{--fail-on-breach : Exit non-zero if any route class is out of SLO}';

    protected $description = 'Phase-22 PERF-SLO-3: p50/p95/p99 HTTP latency + SLO compliance per route class.';

    public function handle(MetricsService $metrics): int
    {
        $sinceDays = $this->resolveSinceDays();
        $buckets = $this->mergeBuckets($metrics, $sinceDays);

        if ($buckets === []) {
            $this->info("slo:report: no http_request_ms samples in the last {$sinceDays} day-bucket(s).");

            return self::SUCCESS;
        }

        // Aggregate cumulative histogram buckets per route, then roll
        // routes up into their SLO route class.
        $perRoute = $this->aggregatePerRoute($buckets);
        $perClass = $this->aggregatePerClass($perRoute);
        $perClass = $this->applyClassFilter($perClass);

        [$rows, $anyBreach] = $this->buildRows($perClass);

        $this->emitOutput($rows, $anyBreach, $sinceDays);

        return $this->resolveExitCode($anyBreach);
    }

    /**
     * Group the http_request_ms_bucket / _count fields by route.
     *
     * @param  array<string, int|float>  $fields
     * @return array<string, array{class:string, buckets:array<string,int>, count:int}>
     */
    private function aggregatePerRoute(array $fields): array
    {
        $routes = [];
        foreach ($fields as $field => $value) {
            [$metric, $labels] = $this->parseField($field);
            $route = $labels['route'] ?? 'unmatched';
            $method = $labels['method'] ?? 'GET';

            $this->ensureRouteEntry($routes, $route, $method);
            $this->accumulateRouteValue($routes, $route, ['metric' => $metric, 'labels' => $labels, 'value' => $value]);
        }

        return $routes;
    }

    /**
     * Prometheus-style histogram_quantile: the bucket counts are
     * cumulative (MetricsService::observe increments every bucket whose
     * le >= value), so find the bucket where the cumulative count
     * crosses the target rank and linear-interpolate within it.
     *
     * @param  array<string, int>  $buckets  le => cumulative count
     */
    private function percentile(array $buckets, int $count, float $q): ?float
    {
        if ($count <= 0 || $buckets === []) {
            return null;
        }

        $bounds = $this->sortedBounds($buckets);
        $rank = $q * $count;

        return $this->walkBoundsForRank($buckets, $bounds, $rank);
    }

    private function resolveSinceDays(): int
    {
        $sinceDays = (int) ($this->option('since') ?: config('observability.slo.evaluation_window_days', 1));

        return max(1, $sinceDays);
    }

    /**
     * @param  array<string, array{buckets:array<string,int>, count:int}>  $perClass
     * @return array{0: list<array<string,mixed>>, 1: bool}
     */
    private function buildRows(array $perClass): array
    {
        $rows = [];
        $anyBreach = false;

        foreach ($perClass as $class => $hist) {
            $budget = RouteClassResolver::budgetMsFor($class);
            $p95 = $this->percentile($hist['buckets'], $hist['count'], 0.95);
            $breached = $budget !== null && $p95 !== null && $p95 > $budget;
            $anyBreach = $anyBreach || $breached;
            $rows[] = $this->buildRow($class, $hist, ['budget' => $budget, 'p95' => $p95, 'breached' => $breached]);
        }

        return [$rows, $anyBreach];
    }

    /**
     * @param  array{buckets:array<string,int>, count:int}  $hist
     * @param  array{budget:?int, p95:?float, breached:bool}  $slo
     * @return array<string, mixed>
     */
    private function buildRow(string $class, array $hist, array $slo): array
    {
        return [
            'route_class' => $class,
            'count' => $hist['count'],
            'p50_ms' => $this->fmt($this->percentile($hist['buckets'], $hist['count'], 0.50)),
            'p95_ms' => $this->fmt($slo['p95']),
            'p99_ms' => $this->fmt($this->percentile($hist['buckets'], $hist['count'], 0.99)),
            'budget_ms' => $slo['budget'] ?? '—',
            'slo' => $slo['budget'] === null ? 'n/a' : ($slo['breached'] ? 'OUT OF SLO' : 'IN SLO'),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function emitOutput(array $rows, bool $anyBreach, int $sinceDays): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'since_days' => $sinceDays,
                'route_classes' => $rows,
                'any_breach' => $anyBreach,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Route class', 'Count', 'p50 (ms)', 'p95 (ms)', 'p99 (ms)', 'Budget (ms)', 'SLO'],
                array_map('array_values', $rows),
            );
        }
    }

    private function resolveExitCode(bool $anyBreach): int
    {
        if ($anyBreach && $this->option('fail-on-breach')) {
            $this->error('slo:report: one or more route classes are OUT OF SLO.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{class:string, buckets:array<string,int>, count:int}>  $perClass
     * @return array<string, array{buckets:array<string,int>, count:int}>
     */
    private function applyClassFilter(array $perClass): array
    {
        $filterClass = $this->option('route-class');
        if ($filterClass === null) {
            return $perClass;
        }

        return array_filter(
            $perClass,
            fn (string $class) => $class === $filterClass,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Merge the Redis metric hashes for the last N day-buckets into one
     * field => value map (later days overwrite — but field names are
     * date-independent so values from distinct days are summed).
     *
     * @return array<string, int|float>
     */
    private function mergeBuckets(MetricsService $metrics, int $sinceDays): array
    {
        $merged = [];
        for ($i = 0; $i < $sinceDays; $i++) {
            $day = now()->subDays($i)->format('Y-m-d');
            foreach ($metrics->snapshot($day) as $field => $value) {
                if (! str_starts_with($field, 'http_request_ms')) {
                    continue;
                }
                $merged[$field] = ($merged[$field] ?? 0) + (is_numeric($value) ? $value + 0 : 0);
            }
        }

        return $merged;
    }

    /**
     * Roll per-route histograms up into per-route-class histograms.
     *
     * @param  array<string, array{class:string, buckets:array<string,int>, count:int}>  $perRoute
     * @return array<string, array{buckets:array<string,int>, count:int}>
     */
    private function aggregatePerClass(array $perRoute): array
    {
        $classes = [];
        foreach ($perRoute as $data) {
            $class = $data['class'];
            if (! isset($classes[$class])) {
                $classes[$class] = ['buckets' => [], 'count' => 0];
            }
            $classes[$class]['count'] += $data['count'];
            foreach ($data['buckets'] as $le => $count) {
                $classes[$class]['buckets'][$le] = ($classes[$class]['buckets'][$le] ?? 0) + $count;
            }
        }

        return $classes;
    }

    /**
     * Split a MetricsService field name into [metric, labels].
     *
     * @return array{0:string, 1:array<string,string>}
     */
    private function parseField(string $field): array
    {
        $bracePos = strpos($field, '{');
        if ($bracePos === false) {
            return [$field, []];
        }

        $metric = substr($field, 0, $bracePos);
        $labelStr = trim(substr($field, $bracePos + 1), '{}');
        $labels = [];
        foreach (explode(',', $labelStr) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $labels[$k] = $v;
        }

        return [$metric, $labels];
    }

    private function fmt(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1);
    }

    /**
     * @param  array<string, array{class:string, buckets:array<string,int>, count:int}>  $routes
     */
    private function ensureRouteEntry(array &$routes, string $route, string $method): void
    {
        if (! isset($routes[$route])) {
            $routes[$route] = [
                'class' => RouteClassResolver::classify($route === 'unmatched' ? null : $route, $method),
                'buckets' => [],
                'count' => 0,
            ];
        }
    }

    /**
     * Accumulate a parsed histogram field (bucket or count) into the route entry.
     *
     * @param  array<string, array{class:string, buckets:array<string,int>, count:int}>  $routes
     * @param  array{metric:string, labels:array<string,string>, value:mixed}  $parsed
     */
    private function accumulateRouteValue(array &$routes, string $route, array $parsed): void
    {
        $metric = $parsed['metric'];
        $labels = $parsed['labels'];
        $value = $parsed['value'];

        if ($metric === 'http_request_ms_bucket' && isset($labels['le'])) {
            $le = $labels['le'];
            $routes[$route]['buckets'][$le] = ($routes[$route]['buckets'][$le] ?? 0) + (int) $value;
        } elseif ($metric === 'http_request_ms_count') {
            $routes[$route]['count'] += (int) $value;
        }
    }

    /**
     * Return bucket bounds sorted ascending with +Inf last.
     *
     * @param  array<string, int>  $buckets
     * @return list<string>
     */
    private function sortedBounds(array $buckets): array
    {
        $bounds = array_keys($buckets);
        usort($bounds, function ($a, $b) {
            if ($a === '+Inf') {
                return 1;
            }
            if ($b === '+Inf') {
                return -1;
            }

            return (float) $a <=> (float) $b;
        });

        return $bounds;
    }

    /**
     * Walk sorted bucket bounds to find the bound where cumulative count
     * crosses the target rank, then interpolate.
     *
     * @param  array<string, int>  $buckets
     * @param  list<string>  $bounds
     */
    private function walkBoundsForRank(array $buckets, array $bounds, float $rank): ?float
    {
        $prevBound = 0.0;
        $prevCum = 0;

        foreach ($bounds as $bound) {
            $cum = $buckets[$bound];
            if ($cum >= $rank) {
                return $this->interpolateWithinBucket($bound, $prevBound, ['prevCum' => $prevCum, 'cum' => $cum, 'rank' => $rank]);
            }
            $prevBound = $this->advanceBound($bound, $prevBound);
            $prevCum = $cum;
        }

        return $prevBound > 0 ? $prevBound : null;
    }

    /** Advance the lower-bound tracker, keeping +Inf bounds from changing it. */
    private function advanceBound(int|string $bound, float $prevBound): float
    {
        return $bound === '+Inf' ? $prevBound : (float) $bound;
    }

    /**
     * Linear-interpolate the quantile within a histogram bucket.
     *
     * @param  array{prevCum:int, cum:int, rank:float}  $span
     */
    private function interpolateWithinBucket(int|string $bound, float $prevBound, array $span): ?float
    {
        if ($bound === '+Inf') {
            return $prevBound > 0 ? $prevBound : null;
        }
        $upper = (float) $bound;
        $spanCount = $span['cum'] - $span['prevCum'];
        if ($spanCount <= 0) {
            return $upper;
        }

        return $prevBound + ($upper - $prevBound) * (($span['rank'] - $span['prevCum']) / $spanCount);
    }
}
