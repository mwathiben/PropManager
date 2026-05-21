<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

use Illuminate\Validation\ValidationException;

/**
 * Phase-74 CARD-TYPES: a bar chart from a saved report's rows — a label column
 * + a numeric value column. No charting dependency (the Vue renders div bars,
 * matching ReportsTab). Points are capped to keep the payload + render bounded.
 * Fail-closed if the chosen fields aren't present in the report's output.
 */
class ChartCardRenderer extends AbstractCardRenderer
{
    private const MAX_POINTS = 50;

    public function type(): string
    {
        return 'chart';
    }

    public function validate(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $labelField = $this->requireField($index, $card['label_field'] ?? null, 'label_field');
        $valueField = $this->requireField($index, $card['value_field'] ?? null, 'value_field');

        $normalised = [
            'type' => 'chart',
            'saved_report_id' => $report->id,
            'label_field' => $labelField,
            'value_field' => $valueField,
            'size' => $this->validateSize($card['size'] ?? 'wide'),
        ];
        if (isset($card['title']) && is_string($card['title']) && $card['title'] !== '') {
            $normalised['title'] = mb_substr($card['title'], 0, 200);
        }

        return $normalised;
    }

    public function render(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $labelField = $this->requireField($index, $card['label_field'] ?? null, 'label_field');
        $valueField = $this->requireField($index, $card['value_field'] ?? null, 'value_field');

        $rows = $this->builder->run($report->config, $landlordId);

        if ($rows !== []) {
            $first = $rows[0];
            foreach (['label_field' => $labelField, 'value_field' => $valueField] as $key => $field) {
                if (! array_key_exists($field, $first)) {
                    throw ValidationException::withMessages([
                        "layout.{$index}.{$key}" => "Field '{$field}' is not in this report's output.",
                    ]);
                }
            }
        }

        $points = [];
        foreach (array_slice($rows, 0, self::MAX_POINTS) as $row) {
            $points[] = [
                'label' => (string) ($row[$labelField] ?? ''),
                'value' => is_numeric($row[$valueField] ?? null) ? (float) $row[$valueField] : 0.0,
            ];
        }

        return [
            'type' => 'chart',
            'title' => $this->stringOr($card['title'] ?? null, $report->name),
            'size' => $this->validateSize($card['size'] ?? 'wide'),
            'saved_report_id' => $report->id,
            'label_field' => $labelField,
            'value_field' => $valueField,
            'points' => $points,
        ];
    }

    public function descriptor(): array
    {
        return ['key' => 'chart', 'label' => 'Chart', 'needs_saved_report' => true, 'needs_metric' => false];
    }

    private function requireField(int $index, mixed $field, string $key): string
    {
        if (! is_string($field) || $field === '') {
            throw ValidationException::withMessages([
                "layout.{$index}.{$key}" => "Chart card requires {$key}.",
            ]);
        }

        return $field;
    }
}
