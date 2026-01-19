<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class StoreWingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $building = $this->route('building');

        return $building && $building->landlord_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'unit_prefix' => 'required|string|max:3',
            'floors' => 'required|integer|min:1|max:100',
            'units_per_floor' => 'required|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'floors.max' => 'Wing cannot have more than 100 floors.',
            'units_per_floor.max' => 'Each floor cannot have more than 50 units.',
        ];
    }
}
