<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'selectedUnitIds' => 'required|array',
            'action' => 'required|string',
            'value' => 'nullable',
        ];
    }
}
