<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;

class RejectWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->role === 'landlord';
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required when rejecting a water reading.',
        ];
    }
}
