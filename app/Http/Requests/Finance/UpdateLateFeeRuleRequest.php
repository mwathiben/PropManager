<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLateFeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'property_id' => 'nullable|exists:properties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'grace_period_days' => 'required|integer|min:0|max:60',
            'fee_type' => 'required|in:percentage,flat_amount',
            'fee_percentage' => 'required_if:fee_type,percentage|nullable|numeric|min:0|max:100',
            'fee_amount' => 'required_if:fee_type,flat_amount|nullable|numeric|min:0',
            'is_compounding' => 'boolean',
            'compounding_frequency' => 'nullable|in:daily,weekly,monthly',
            'max_fee_cap' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
