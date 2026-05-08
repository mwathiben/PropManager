<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'selectedUnitIds' => 'required|array',
            'selectedUnitIds.*' => 'integer|exists:units,id',
            'action' => ['required', 'string', Rule::in(['update_rent', 'update_status', 'update_type', 'delete'])],
            'value' => 'nullable|string',
        ];
    }
}
