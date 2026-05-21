<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

use App\Models\ReportMetric;
use App\Models\SavedReport;
use App\Services\Reports\MetricFormulaService;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Validation\ValidationException;

/**
 * Phase-74 CARD-REGISTRY: shared ownership-validation + helper surface for
 * card renderers. The requireSavedReport / requireMetric guards are the
 * cross-tenant boundary (lifted verbatim from the pre-registry DashboardService)
 * — they fetch withoutGlobalScope but pin where('landlord_id', $landlordId), so
 * a tampered layout can never reach another landlord's data.
 */
abstract class AbstractCardRenderer implements DashboardCardRenderer
{
    public function __construct(
        protected ReportBuilderService $builder,
        protected MetricFormulaService $formulas,
    ) {}

    /**
     * @param  array<string, mixed>  $card
     */
    protected function requireSavedReport(int $index, array $card, int $landlordId): SavedReport
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

    protected function requireMetric(int $index, mixed $metricSlug, int $landlordId): ReportMetric
    {
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

        return $metric;
    }

    protected function validateSize(mixed $size): string
    {
        return in_array($size, ['wide', 'narrow'], true) ? (string) $size : 'wide';
    }

    protected function stringOr(mixed $candidate, string $fallback): string
    {
        return is_string($candidate) && $candidate !== '' ? $candidate : $fallback;
    }

    /**
     * Map a report row's keys (payment_amount) to metric field refs
     * (payment.amount) for MetricFormulaService::evaluate.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function fieldKeyed(array $row): array
    {
        $keyed = [];
        foreach ($row as $k => $v) {
            $keyed[str_replace('_', '.', (string) $k)] = $v;
        }

        return $keyed;
    }
}
