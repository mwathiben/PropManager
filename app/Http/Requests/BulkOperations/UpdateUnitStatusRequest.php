<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'new_status' => 'required|in:vacant,occupied,maintenance,arrears',
            'notes' => 'nullable|string|max:500',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:buildings,id',
        ];
    }
}
