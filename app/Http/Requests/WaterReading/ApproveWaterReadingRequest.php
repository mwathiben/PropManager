<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;

class ApproveWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:500',
        ];
    }
}
