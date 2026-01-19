<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingWaterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'water_billing_type' => 'nullable|in:consumption,flat_rate',
            'water_flat_rate' => 'nullable|numeric|min:0|required_if:water_billing_type,flat_rate',
        ];
    }

    public function messages(): array
    {
        return [
            'water_flat_rate.required_if' => 'Flat rate amount is required when using flat rate billing.',
        ];
    }
}
