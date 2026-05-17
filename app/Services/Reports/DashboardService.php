<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LandlordDashboard;
use App\Models\ReportMetric;
use App\Models\SavedReport;
use Illuminate\Validation\ValidationException;

/**
 * Phase-50 LANDLORD-DASHBOARDS-2: assemble a dashboard render payload
 * by running each card's saved-report (or metric+saved-report pair)
 * against the landlord's data.
 *
 * Every card is re-validated for landlord ownership at render time —
 * the layout JSON is opaque storage and could have been tampered with
 * via support edits / direct DB writes. The contract is:
 *
 *   - card.type ∈ {saved_report, metric}
 *   - card.saved_report_id MUST belong to this landlord
 *   - card.metric_slug (when type=metric) MUST exist + be active for
 *     this landlord
 *
 * Anything else throws ValidationException at render time so the
 * dashboard fails closed instead of silently dropping cards.
 */
class DashboardService
{
    public function __construct(
        protected ReportBuilderService $builder,
        protected MetricFormulaService $formulas,
    ) {}

    /**
     * @return array{
     *   dashboard: array{id: int, slug: string, name: string, description: ?string},
     *   cards: list<array<string, mixed>>
     * }
     */
    public function buildPayload(LandlordDashboard $dashboard): array
    {
        $landlordId = (int) $dashboard->landlord_id;
        $layout = $dashboard->layout ?? [];
        if (! is_array($layout)) {
            throw ValidationException::withMessages(['layout' => 'Dashboard layout is malformed.']);
        }

        $cards = [];
        foreach ($layout as $i => $cardRaw) {
            if (! is_array($cardRaw)) {
                throw ValidationException::withMessages(["layout.{$i}" => 'Card must be an object.']);
            }
            $cards[] = $this->renderCard($i, $cardRaw, $landlordId);
        }

        return [
            'dashboard' => [
                'id' => $dashboard->id,
                'slug' => $dashboard->slug,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
            ],
            'cards' => $cards,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function renderCard(int $index, array $card, int $landlordId): array
    {
        $type = $card['type'] ?? null;
        if (! is_string($type) || ! in_array($type, ['saved_report', 'metric'], true)) {
            throw ValidationException::withMessages([
                "layout.{$index}.type" => "Card type must be 'saved_report' or 'metric'.",
            ]);
        }

        $savedReport = $this->requireSavedReport($index, $card, $landlordId);

        $rows = $this->builder->run($savedReport->config, $landlordId);

        if ($type === 'saved_report') {
            return [
                'type' => 'saved_report',
                'title' => $this->stringOr($card['title'] ?? null, $savedReport->name),
                'size' => $this->validateSize($card['size'] ?? 'wide'),
                'saved_report_id' => $savedReport->id,
                'rows' => $rows,
            ];
        }

        $metricSlug = $card['metric_slug'] ?? null;
        if (! is_string($metricSlug) || $metricSlug === '') {
            throw ValidationException::withMessages([
                "layout.{$index}.metric_slug" => 'Metric card requires metric_slug.',
            ]);
        }

        $metric = ReportMetric::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('slug', $metricSlug)
            ->where('is_active', true)
            ->first(['name', 'slug', 'parsed_rpn', 'unit']);

        if (! $metric) {
            throw ValidationException::withMessages([
                "layout.{$index}.metric_slug" => "Metric '{$metricSlug}' is unknown or inactive.",
            ]);
        }

        $values = [];
        foreach ($rows as $row) {
            $fieldKeyed = [];
            foreach ($row as $k => $v) {
                $fieldKeyed[str_replace('_', '.', (string) $k)] = $v;
            }
            try {
                $values[] = $this->formulas->evaluate($metric->parsed_rpn, $fieldKeyed);
            } catch (ValidationException) {
                // Skip rows missing the referenced field — the average
                // should reflect rows where the metric is computable.
            }
        }

        $average = $values === [] ? null : array_sum($values) / count($values);

        return [
            'type' => 'metric',
            'title' => $this->stringOr($card['title'] ?? null, $metric->name),
            'size' => $this->validateSize($card['size'] ?? 'narrow'),
            'metric_slug' => $metric->slug,
            'saved_report_id' => $savedReport->id,
            'unit' => $metric->unit,
            'count' => count($values),
            'average' => $average,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function requireSavedReport(int $index, array $card, int $landlordId): SavedReport
    {
        $reportId = $card['saved_report_id'] ?? null;
        if (! is_int($reportId)) {
            throw ValidationException::withMessages([
                "layout.{$index}.saved_report_id" => 'saved_report_id must be an integer.',
            ]);
        }

        $report = SavedReport::query()
            ->withoutGlobalScope('landlord')
            ->where('id', $reportId)
            ->where('landlord_id', $landlordId)
            ->first(['id', 'name', 'config']);

        if (! $report) {
            throw ValidationException::withMessages([
                "layout.{$index}.saved_report_id" => "Saved report #{$reportId} not found for this landlord.",
            ]);
        }

        return $report;
    }

    private function validateSize(mixed $size): string
    {
        return in_array($size, ['wide', 'narrow'], true) ? (string) $size : 'wide';
    }

    private function stringOr(mixed $candidate, string $fallback): string
    {
        return is_string($candidate) && $candidate !== '' ? $candidate : $fallback;
    }
}
