<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

use Illuminate\Validation\ValidationException;

/**
 * Phase-74 CARD-TYPES: a single big-number KPI tile. Like a metric card but
 * collapses the metric's per-row values to ONE aggregate (sum|avg|min|max|count)
 * instead of an average + count table. Bound to a saved_report + metric_slug;
 * ownership-validated through AbstractCardRenderer.
 */
class KpiCardRenderer extends AbstractCardRenderer
{
    private const AGGS = ['sum', 'avg', 'min', 'max', 'count'];

    public function type(): string
    {
        return 'kpi';
    }

    public function validate(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $metric = $this->requireMetric($index, $card['metric_slug'] ?? null, $landlordId);

        $normalised = [
            'type' => 'kpi',
            'saved_report_id' => $report->id,
            'metric_slug' => $metric->slug,
            'agg' => $this->validateAgg($index, $card['agg'] ?? 'avg'),
            'size' => $this->validateSize($card['size'] ?? 'narrow'),
        ];
        if (isset($card['title']) && is_string($card['title']) && $card['title'] !== '') {
            $normalised['title'] = mb_substr($card['title'], 0, 200);
        }

        return $normalised;
    }

    public function render(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $metric = $this->requireMetric($index, $card['metric_slug'] ?? null, $landlordId);
        $agg = $this->validateAgg($index, $card['agg'] ?? 'avg');

        $rows = $this->builder->run($report->config, $landlordId);

        $values = [];
        foreach ($rows as $row) {
            try {
                $values[] = $this->formulas->evaluate($metric->parsed_rpn, $this->fieldKeyed($row));
            } catch (ValidationException) {
                // Skip rows where the metric isn't computable.
            }
        }

        return [
            'type' => 'kpi',
            'title' => $this->stringOr($card['title'] ?? null, $metric->name),
            'size' => $this->validateSize($card['size'] ?? 'narrow'),
            'metric_slug' => $metric->slug,
            'saved_report_id' => $report->id,
            'unit' => $metric->unit,
            'agg' => $agg,
            'count' => count($values),
            'value' => $this->aggregate($agg, $values),
        ];
    }

    public function descriptor(): array
    {
        return ['key' => 'kpi', 'label' => 'KPI tile', 'needs_saved_report' => true, 'needs_metric' => true];
    }

    private function validateAgg(int $index, mixed $agg): string
    {
        if (! is_string($agg) || ! in_array($agg, self::AGGS, true)) {
            throw ValidationException::withMessages([
                "layout.{$index}.agg" => 'KPI aggregate must be one of: '.implode(', ', self::AGGS).'.',
            ]);
        }

        return $agg;
    }

    /**
     * @param  list<float>  $values
     */
    private function aggregate(string $agg, array $values): ?float
    {
        if ($agg === 'count') {
            return (float) count($values);
        }
        if ($values === []) {
            return null;
        }

        return match ($agg) {
            'sum' => array_sum($values),
            'avg' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
            default => null,
        };
    }
}
