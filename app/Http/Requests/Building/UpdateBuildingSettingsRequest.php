<?php

namespace App\Http\Requests\Building;

use App\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('building'));
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'building_type' => 'required|string|in:'.implode(',', array_keys(Building::BUILDING_TYPES)),
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'amenities' => 'nullable|array',
            'amenities.selected' => 'nullable|array',
            'amenities.custom' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'coordinates.lat' => 'nullable|numeric|between:-90,90',
            'coordinates.lng' => 'nullable|numeric|between:-180,180',
            'photos' => 'nullable|array',
            // Phase-17 MONEY-9: KES-only until Phase-18 FX support ships.
            'currency' => ['nullable', 'string', Rule::in(['KES'])],
        ];
    }
}
