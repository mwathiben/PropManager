<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    // VALID-6: notification templates are landlord-scoped configuration.
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ];
    }
}
