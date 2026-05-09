<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppTemplatesRequest extends FormRequest
{
    // VALID-6: WhatsApp template SIDs are landlord-scoped credentials.
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
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
