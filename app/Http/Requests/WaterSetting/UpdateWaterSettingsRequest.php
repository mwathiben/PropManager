<?php

namespace App\Http\Requests\WaterSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'water_billing_type' => 'required|in:consumption,flat_rate,none',
            'water_unit_rate' => 'nullable|numeric|min:0',
            'flat_water_rate' => 'nullable|numeric|min:0',
            // Phase-87 tariff depth (global).
            'tiered_tariffs' => 'nullable|array',
            'tiered_tariffs.*.from' => 'required_with:tiered_tariffs|numeric|min:0',
            'tiered_tariffs.*.to' => 'nullable|numeric|min:0',
            'tiered_tariffs.*.rate' => 'required_with:tiered_tariffs|numeric|min:0',
            'water_standing_charge' => 'nullable|numeric|min:0',
            'water_minimum_charge' => 'nullable|numeric|min:0',
            'water_sewerage_percent' => 'nullable|numeric|min:0|max:100',
            'water_vat_percent' => 'nullable|numeric|min:0|max:100',
            'water_source' => 'nullable|in:borehole,county,mixed',
            'building_overrides' => 'nullable|array',
            'building_overrides.*.id' => 'required|exists:buildings,id',
            'building_overrides.*.water_billing_type' => 'nullable|in:consumption,flat_rate,none,inherit',
            'building_overrides.*.water_unit_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_flat_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_standing_charge' => 'nullable|numeric|min:0',
            'building_overrides.*.water_minimum_charge' => 'nullable|numeric|min:0',
            'building_overrides.*.water_sewerage_percent' => 'nullable|numeric|min:0|max:100',
            'building_overrides.*.water_vat_percent' => 'nullable|numeric|min:0|max:100',
            'building_overrides.*.water_source' => 'nullable|in:borehole,county,mixed',
        ];
    }
}
