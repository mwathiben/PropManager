<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

use Illuminate\Validation\ValidationException;

class MetricCardRenderer extends AbstractCardRenderer
{
    public function type(): string
    {
        return 'metric';
    }

    public function validate(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $metric = $this->requireMetric($index, $card['metric_slug'] ?? null, $landlordId);

        $normalised = [
            'type' => 'metric',
            'saved_report_id' => $report->id,
            'metric_slug' => $metric->slug,
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

        $rows = $this->builder->run($report->config, $landlordId);

        $values = [];
        foreach ($rows as $row) {
            try {
                $values[] = $this->formulas->evaluate($metric->parsed_rpn, $this->fieldKeyed($row));
            } catch (ValidationException) {
                // Skip rows missing the referenced field — the average reflects
                // rows where the metric is computable.
            }
        }

        $average = $values === [] ? null : array_sum($values) / count($values);

        return [
            'type' => 'metric',
            'title' => $this->stringOr($card['title'] ?? null, $metric->name),
            'size' => $this->validateSize($card['size'] ?? 'narrow'),
            'metric_slug' => $metric->slug,
            'saved_report_id' => $report->id,
            'unit' => $metric->unit,
            'count' => count($values),
            'average' => $average,
        ];
    }

    public function descriptor(): array
    {
        return ['key' => 'metric', 'label' => 'Metric', 'needs_saved_report' => true, 'needs_metric' => true];
    }
}
