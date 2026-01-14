<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isLandlord() || $user->isCaretaker();
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
            'month.required' => 'Billing month is required.',
            'month.integer' => 'Month must be a number.',
            'month.min' => 'Month must be between 1 and 12.',
            'month.max' => 'Month must be between 1 and 12.',
            'year.required' => 'Billing year is required.',
            'year.integer' => 'Year must be a number.',
            'year.min' => 'Year must be 2020 or later.',
            'year.max' => 'Year cannot exceed 2100.',
        ];
    }
}
