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
            'building_overrides' => 'nullable|array',
            'building_overrides.*.id' => 'required|exists:buildings,id',
            'building_overrides.*.water_billing_type' => 'nullable|in:consumption,flat_rate,none,inherit',
            'building_overrides.*.water_unit_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_flat_rate' => 'nullable|numeric|min:0',
        ];
    }
}
