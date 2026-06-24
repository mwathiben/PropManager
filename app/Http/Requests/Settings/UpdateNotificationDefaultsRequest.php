<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner();
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
            'maintenance_notice_enabled' => 'boolean',
            'general_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'rent_reminder_days_before' => 'nullable|integer|min:1|max:30',
        ];
    }
}
