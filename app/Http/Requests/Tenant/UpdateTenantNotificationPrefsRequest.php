<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase-28 TENANT-PROFILE-2: tenant-side surface over the existing
 * NotificationPreference matrix (type × channel AND-gated). The tenant
 * can toggle the 8 tenant-relevant notification types and the 5
 * channels; whatsapp_number is captured only when whatsapp_enabled is
 * true (E.164 format enforced by the model mutator). Landlord-only
 * notification types (eviction_notice, caretaker_invitation,
 * tenant_invitation, rent_hike) are NOT exposed here.
 */
class UpdateTenantNotificationPrefsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTenant() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rent_reminder_enabled' => ['required', 'boolean'],
            'arrears_notice_enabled' => ['required', 'boolean'],
            'invoice_enabled' => ['required', 'boolean'],
            'receipt_enabled' => ['required', 'boolean'],
            'lease_expiry_enabled' => ['required', 'boolean'],
            'lease_renewal_enabled' => ['required', 'boolean'],
            'maintenance_notice_enabled' => ['required', 'boolean'],
            'general_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'sms_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'in_app_enabled' => ['required', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}
