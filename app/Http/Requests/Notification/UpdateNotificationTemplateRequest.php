<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    // VALID-6: route-model ownership check on the template being edited.
    public function authorize(): bool
    {
        $user = $this->user();
        $template = $this->route('template');

        if (! $user || ! $template) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $template->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ];
    }
}
