<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class StartMoveOutInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_move_out_date' => 'required|date',
        ];
    }
}
