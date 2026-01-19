<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMoveOutDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
