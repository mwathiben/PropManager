<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:none,ocr_space,google_vision,azure_vision,tesseract',
            'enabled' => 'required|boolean',
            'auto_verify' => 'required|boolean',
            'api_key' => 'nullable|string',
            'azure_endpoint' => 'nullable|string|url',
        ];
    }
}
