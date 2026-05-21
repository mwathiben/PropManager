<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use App\Services\Reports\NoiService;

/**
 * Phase-78 PROPERTY-BENCHMARK: rank each property against the rest of the
 * landlord's portfolio on three comparable yardsticks — occupancy, NOI margin
 * and gross yield (annualised rent roll / estimated value). Built on top of
 * PropertyMetricsService (occupancy + rent roll) and NoiService (margin), so
 * the math lives in one place and the surface only renders.
 *
 * Percentile rank is "higher is better": the fraction of OTHER properties this
 * one beats, 0–100. With a single property there is nothing to compare against,
 * so percentiles are null (and the overall rank is 1).
 */
class PropertyBenchmarkService
{
    public function __construct(
        private readonly PropertyMetricsService $metrics,
        private readonly NoiService $noi,
    ) {}

    /**
     * @return array{
     *   properties: list<array{property_id:int, name:string, occupancy_pct:float, noi_margin:float|null, gross_yield:float|null, occupancy_percentile:float|null, margin_percentile:float|null, yield_percentile:float|null, rank:int}>,
     *   portfolio: array{property_count:int, avg_occupancy_pct:float, median_occupancy_pct:float, median_noi_margin:float|null, median_gross_yield:float|null}
     * }
     */
    public function forLandlord(int $landlordId): array
    {
        $metrics = collect($this->metrics->forLandlord($landlordId))->keyBy('property_id');

        if ($metrics->isEmpty()) {
            return [
                'properties' => [],
                'portfolio' => [
                    'property_count' => 0,
                    'avg_occupancy_pct' => 0.0,
                    'median_occupancy_pct' => 0.0,
                    'median_noi_margin' => null,
                    'median_gross_yield' => null,
                ],
            ];
        }

        $noiMargins = collect($this->noi->byProperty($landlordId)['properties'])
            ->pluck('noi_margin', 'property_id');

        $values = Property::query()
            ->where('landlord_id', $landlordId)
            ->pluck('estimated_value', 'id');

        $rows = $metrics->map(function (array $m) use ($noiMargins, $values) {
            $value = $values[$m['property_id']] !== null ? (float) $values[$m['property_id']] : null;
            $annualRent = $m['monthly_rent_roll'] * 12;
            $grossYield = $value !== null && $value > 0 ? round($annualRent / $value, 4) : null;
            $margin = $noiMargins[$m['property_id']] ?? null;

            return [
                'property_id' => $m['property_id'],
                'name' => $m['name'],
                'occupancy_pct' => $m['occupancy_pct'],
                'noi_margin' => $margin !== null ? round((float) $margin, 4) : null,
                'gross_yield' => $grossYield,
            ];
        })->values();

        $occPercentiles = $this->percentiles($rows->pluck('occupancy_pct')->all());
        $marginPercentiles = $this->percentiles($rows->pluck('noi_margin')->all());
        $yieldPercentiles = $this->percentiles($rows->pluck('gross_yield')->all());

        $ranked = $rows->map(function (array $row, int $i) use ($occPercentiles, $marginPercentiles, $yieldPercentiles) {
            $parts = array_values(array_filter(
                [$occPercentiles[$i], $marginPercentiles[$i], $yieldPercentiles[$i]],
                fn ($p) => $p !== null,
            ));

            return [
                ...$row,
                'occupancy_percentile' => $occPercentiles[$i],
                'margin_percentile' => $marginPercentiles[$i],
                'yield_percentile' => $yieldPercentiles[$i],
                'score' => $parts === [] ? 0.0 : array_sum($parts) / count($parts),
            ];
        });

        $order = $ranked->sortByDesc('score')->values();
        $rankById = [];
        foreach ($order as $i => $row) {
            $rankById[$row['property_id']] = $i + 1;
        }

        $properties = $ranked->map(function (array $row) use ($rankById) {
            unset($row['score']);
            $row['rank'] = $rankById[$row['property_id']];

            return $row;
        })->all();

        return [
            'properties' => $properties,
            'portfolio' => [
                'property_count' => $rows->count(),
                'avg_occupancy_pct' => round((float) $rows->avg('occupancy_pct'), 1),
                'median_occupancy_pct' => round((float) $this->median($rows->pluck('occupancy_pct')->all()), 1),
                'median_noi_margin' => $this->medianOrNull($rows->pluck('noi_margin')->all()),
                'median_gross_yield' => $this->medianOrNull($rows->pluck('gross_yield')->all()),
            ],
        ];
    }

    /**
     * Percentile rank per value: the fraction of OTHER non-null values this one
     * is strictly greater than, scaled 0–100. Null values (and the single-value
     * case) yield null — nothing meaningful to rank against.
     *
     * @param  list<float|null>  $values
     * @return list<float|null>
     */
    private function percentiles(array $values): array
    {
        $present = array_values(array_filter($values, fn ($v) => $v !== null));
        $denominator = count($present) - 1;

        return array_map(function ($v) use ($present, $denominator) {
            if ($v === null || $denominator < 1) {
                return null;
            }

            $beaten = count(array_filter($present, fn ($o) => $o < $v));

            return round($beaten / $denominator * 100, 1);
        }, $values);
    }

    /**
     * @param  list<float|null>  $values
     */
    private function median(array $values): float
    {
        $present = array_values(array_filter($values, fn ($v) => $v !== null));
        if ($present === []) {
            return 0.0;
        }
        sort($present);
        $n = count($present);
        $mid = intdiv($n, 2);

        return $n % 2 === 0
            ? ((float) $present[$mid - 1] + (float) $present[$mid]) / 2
            : (float) $present[$mid];
    }

    /**
     * @param  list<float|null>  $values
     */
    private function medianOrNull(array $values): ?float
    {
        $present = array_values(array_filter($values, fn ($v) => $v !== null));

        return $present === [] ? null : round($this->median($present), 4);
    }
}
