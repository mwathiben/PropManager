<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ForfeitDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
