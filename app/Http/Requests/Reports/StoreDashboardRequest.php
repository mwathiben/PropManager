<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase-73 DASHBOARD-EDITOR: shape validation for a dashboard create/update.
 * The per-card landlord-ownership check is done in the controller via
 * DashboardService::validateLayout (it needs the resolved landlord id and
 * re-queries saved_report/metric ownership, fail-closed).
 */
class StoreDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isLandlord();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'layout' => ['present', 'array', 'max:50'],
            'layout.*' => ['array'],
            'is_default' => ['boolean'],
        ];
    }
}
