<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'floor_number' => 'required|integer|min:1',
            'unit_number' => 'required|string|max:50',
            'target_rent' => 'required|numeric|min:0',
            'unit_type' => ['required', 'string', Rule::in(['residential', 'commercial'])],
        ];
    }
}
