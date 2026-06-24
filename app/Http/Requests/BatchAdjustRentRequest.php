<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchAdjustRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'required|integer|exists:units,id',
            'adjustment_type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_ids.required' => 'Please select at least one unit.',
            'unit_ids.min' => 'Please select at least one unit.',
            'adjustment_type.in' => 'Adjustment type must be either percentage or fixed.',
            'value.min' => 'Adjustment value cannot be negative.',
        ];
    }
}
