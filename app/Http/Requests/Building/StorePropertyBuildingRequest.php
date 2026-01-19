<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'floors' => 'required|integer|min:1',
            'units_per_floor' => 'required|integer|min:1',
        ];
    }
}
