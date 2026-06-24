<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class WaiveLateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Please provide a detailed reason (at least 10 characters).',
        ];
    }
}
