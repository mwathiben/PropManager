<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoveOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notice_date' => 'required|date',
            'intended_move_out_date' => 'required|date|after_or_equal:notice_date',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
