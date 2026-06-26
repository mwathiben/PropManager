<?php

declare(strict_types=1);

namespace App\Services\Sre;

/**
 * Phase-57 P95-BUDGETS-2: pure-function p95 evaluator over Phase-22's
 * http_request_ms histogram.
 *
 * Input shape: $histogramBuckets is a map of bucket field-name => count,
 * where each field encodes the route_class + le upper bound, e.g.
 *   "http_request_ms_bucket{route_class=read_path,le=500}" => 1234
 *
 * Output shape: [route_class => ['observed_p95_ms' => float,
 *                                'budget_ms' => int,
 *                                'is_violating' => bool]]
 *
 * p95 is computed via linear interpolation between bucket boundaries:
 *   - bucketize: sort buckets by le ascending
 *   - find the first bucket where cumulative count >= 0.95 * total
 *   - linear-interpolate between the prior bucket's upper bound and
 *     this bucket's upper bound based on where 0.95 lands in the
 *     fractional position
 */
class BudgetEnforcementService
{
    /**
     * @param  array<string, int|string>  $histogramBuckets  Redis hash snapshot of `http_request_ms_bucket` fields.
     * @param  array<string, int>  $budgets  route_class => budget_ms (e.g. read_path => 500).
     * @return array<string, array{observed_p95_ms: float, budget_ms: int, is_violating: bool}>
     */
    public function evaluate(array $histogramBuckets, array $budgets): array
    {
        $grouped = $this->groupByRouteClass($histogramBuckets);

        $result = [];
        foreach ($grouped as $routeClass => $bucketsForClass) {
            $budget = $budgets[$routeClass] ?? null;
            if ($budget === null) {
                continue;
            }
            $observed = $this->computeP95($bucketsForClass);
            $result[$routeClass] = [
                'observed_p95_ms' => $observed,
                'budget_ms' => $budget,
                'is_violating' => $observed > $budget,
            ];
        }

        return $result;
    }

    /**
     * Parse fields like `http_request_ms_bucket{route_class=read_path,le=500}`
     * into a {route_class => [le_bound => count]} structure.
     *
     * @return array<string, array<string, int>>
     */
    private function groupByRouteClass(array $histogramBuckets): array
    {
        $grouped = [];
        foreach ($histogramBuckets as $field => $count) {
            if (! str_contains((string) $field, '_bucket{')) {
                continue;
            }
            if (! preg_match('/route_class=([a-zA-Z_]+)/', (string) $field, $rcMatch)) {
                continue;
            }
            if (! preg_match('/le=(\+Inf|[0-9.]+)/', (string) $field, $leMatch)) {
                continue;
            }
            $routeClass = $rcMatch[1];
            $le = $leMatch[1];
            $grouped[$routeClass][$le] = (int) $count;
        }

        return $grouped;
    }

    /**
     * @param  array<string, int>  $bucketsForClass
     */
    private function computeP95(array $bucketsForClass): float
    {
        if ($bucketsForClass === []) {
            return 0.0;
        }

        [$finiteBuckets, $total] = $this->separateAndTotal($bucketsForClass);

        if ($total <= 0) {
            return 0.0;
        }

        return $this->interpolateP95($finiteBuckets, 0.95 * $total);
    }

    /**
     * Split raw buckets into sorted finite-bound buckets and resolve the total count.
     *
     * @param  array<string, int>  $bucketsForClass
     * @return array{array<float, int>, int} [finiteBuckets sorted by bound asc, total]
     */
    private function separateAndTotal(array $bucketsForClass): array
    {
        $finiteBuckets = [];
        $infCount = 0;
        foreach ($bucketsForClass as $le => $count) {
            if ($le === '+Inf') {
                $infCount = $count;

                continue;
            }
            $finiteBuckets[(float) $le] = $count;
        }
        ksort($finiteBuckets);

        $total = $infCount > 0 ? $infCount : max(array_values($finiteBuckets) ?: [0]);

        return [$finiteBuckets, $total];
    }

    /**
     * Walk sorted cumulative buckets and return the linearly-interpolated value at $target count.
     *
     * @param  array<float, int>  $finiteBuckets  sorted by bound ascending
     */
    private function interpolateP95(array $finiteBuckets, float $target): float
    {
        $prevBound = 0.0;
        $prevCount = 0;
        foreach ($finiteBuckets as $upperBound => $cumulative) {
            if ($cumulative >= $target) {
                $bucketSize = max(1, $cumulative - $prevCount);
                $positionInBucket = ($target - $prevCount) / $bucketSize;

                return round($prevBound + ($upperBound - $prevBound) * $positionInBucket, 2);
            }
            $prevBound = (float) $upperBound;
            $prevCount = $cumulative;
        }

        return $prevBound;
    }
}
