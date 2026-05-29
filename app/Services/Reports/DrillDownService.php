<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\SavedReport;
use InvalidArgumentException;

/**
 * Phase-50 DRILL-DOWN-1: synthesises a child report config by appending a
 * filter on the parent's drill_field matching the requested segment value.
 *
 * The parent.drill_field MUST be in ReportBuilderService::ALLOWED_FIELDS —
 * we re-validate even though the saved row was validated at write time,
 * because saved_reports rows can be edited via direct DB queries during
 * support / debugging.
 *
 * Returns the array of result rows from ReportBuilderService::run; the
 * controller wraps the result + persists a child SavedReport with
 * parent_report_id when the landlord wants to "save this drill".
 */
class DrillDownService
{
    public function __construct(
        protected ReportBuilderService $builder,
    ) {}

    public function resolveChild(SavedReport $parent, string $segmentValue): array
    {
        if ($parent->drill_field === null) {
            throw new InvalidArgumentException(
                "SavedReport {$parent->id} has no drill_field — drill-down unavailable."
            );
        }

        if (! array_key_exists($parent->drill_field, ReportBuilderService::ALLOWED_FIELDS)) {
            throw new InvalidArgumentException(
                "Drill field '{$parent->drill_field}' is not in ALLOWED_FIELDS."
            );
        }

        $childConfig = $parent->config;
        $childConfig['filters'] = array_values(array_merge(
            $childConfig['filters'] ?? [],
            [[
                'field' => $parent->drill_field,
                'op' => '=',
                'value' => $segmentValue,
            ]],
        ));

        return [
            'config' => $childConfig,
            'rows' => $this->builder->run($childConfig, $parent->landlord_id),
        ];
    }
}
