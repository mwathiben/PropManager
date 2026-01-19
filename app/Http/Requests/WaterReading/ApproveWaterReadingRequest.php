<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;

class ApproveWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->role === 'landlord';
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:500',
        ];
    }
}
