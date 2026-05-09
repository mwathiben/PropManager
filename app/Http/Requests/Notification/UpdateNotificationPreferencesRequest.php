<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    // VALID-6: per-user notification preferences — any authenticated user.
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rent_reminder_enabled' => 'boolean',
            'arrears_notice_enabled' => 'boolean',
            'invoice_enabled' => 'boolean',
            'receipt_enabled' => 'boolean',
            'rent_hike_enabled' => 'boolean',
            'lease_expiry_enabled' => 'boolean',
            'lease_renewal_enabled' => 'boolean',
            'maintenance_notice_enabled' => 'boolean',
            'general_enabled' => 'boolean',
            'eviction_notice_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'rent_reminder_days_before' => 'nullable|integer|min:1|max:30',
            'preferred_time' => 'nullable|date_format:H:i',
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^(\+?[1-9]\d{1,14}|0[71]\d{8})$/'],
        ];
    }
}
