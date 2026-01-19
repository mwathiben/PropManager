<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'templates' => 'required|array',
            'templates.*.type' => 'required|string|max:50',
            'templates.*.sid' => 'nullable|string|max:100',
        ];
    }
}
