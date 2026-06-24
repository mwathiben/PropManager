<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DeleteApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:ocr_space,google_vision,azure_vision',
        ];
    }
}
