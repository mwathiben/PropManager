<?php

namespace App\Http\Requests;

use App\Models\Building;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'building_type' => 'required|string|in:'.implode(',', array_keys(Building::BUILDING_TYPES)),
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'total_floors' => 'required|integer|min:1|max:100',
            'units_per_floor' => 'required|integer|min:1|max:50',
            'amenities' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'coordinates.lat' => 'nullable|numeric|between:-90,90',
            'coordinates.lng' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'total_floors.min' => 'Building must have at least one floor.',
            'total_floors.max' => 'Building cannot have more than 100 floors.',
            'units_per_floor.min' => 'Each floor must have at least one unit.',
            'units_per_floor.max' => 'Each floor cannot have more than 50 units.',
        ];
    }
}
