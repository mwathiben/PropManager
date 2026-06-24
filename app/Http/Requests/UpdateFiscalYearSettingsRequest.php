<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalYearSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'fiscal_year_type' => 'required|in:calendar,custom',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
        ];
    }

    public function messages(): array
    {
        return [
            'fiscal_year_type.required' => 'Fiscal year type is required.',
            'fiscal_year_type.in' => 'Fiscal year type must be calendar or custom.',
            'fiscal_year_start_month.required' => 'Start month is required.',
            'fiscal_year_start_month.integer' => 'Start month must be a number.',
            'fiscal_year_start_month.min' => 'Start month must be between 1 and 12.',
            'fiscal_year_start_month.max' => 'Start month must be between 1 and 12.',
        ];
    }
}
