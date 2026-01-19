<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class AddUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'floor_number' => 'required',
            'unit_number' => 'required',
            'target_rent' => 'required|numeric',
            'unit_type' => 'required|string',
        ];
    }
}
