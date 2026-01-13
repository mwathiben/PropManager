<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'new_amount' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'new_amount.min' => 'Rent amount cannot be negative.',
            'effective_date.date' => 'Please provide a valid date.',
        ];
    }
}
