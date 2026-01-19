<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
        ];
    }
}
