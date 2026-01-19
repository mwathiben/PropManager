<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMoveOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intended_move_out_date' => 'nullable|date',
            'actual_move_out_date' => 'nullable|date',
            'inspection_notes' => 'nullable|string|max:2000',
        ];
    }
}
