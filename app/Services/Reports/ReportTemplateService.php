<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-50 TEMPLATE-MARKETPLACE-2: clones a platform-curated report
 * template into a landlord-scoped SavedReport. The clone is a deep copy
 * of $template->config so edits on the SavedReport never mutate the
 * source template row.
 *
 * landlord_id on the new SavedReport is taken from the caller arg —
 * NEVER from the template (templates have no landlord_id). The cloning
 * landlord becomes the owner of the new SavedReport.
 */
class ReportTemplateService
{
    public function cloneFor(User $landlord, ReportTemplate $template, ?string $nameOverride = null): SavedReport
    {
        if ($landlord->role !== 'landlord') {
            throw new InvalidArgumentException(
                "Only landlord users may clone report templates; got role '{$landlord->role}'."
            );
        }

        if (! $template->is_active) {
            throw new InvalidArgumentException(
                "Template '{$template->slug}' is inactive and cannot be cloned."
            );
        }

        return DB::transaction(function () use ($landlord, $template, $nameOverride) {
            return SavedReport::create([
                'landlord_id' => $landlord->id,
                'name' => $nameOverride ?? "{$template->name} (copy)",
                'description' => $template->description,
                'config' => $this->deepCopy($template->config),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function deepCopy(array $config): array
    {
        return json_decode(json_encode($config, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
