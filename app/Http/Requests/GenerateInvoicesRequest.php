<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ];
    }

    public function messages(): array
    {
        return [
            'month.required' => __('validation.custom.month.required'),
            'month.integer' => __('validation.custom.month.integer'),
            'month.min' => __('validation.custom.month.min'),
            'month.max' => __('validation.custom.month.max'),
            'year.required' => __('validation.custom.year.required'),
            'year.integer' => __('validation.custom.year.integer'),
            'year.min' => __('validation.custom.year.min'),
            'year.max' => __('validation.custom.year.max'),
        ];
    }
}
