<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationScheduleRequest extends FormRequest
{
    // VALID-6: notification schedules are landlord-scoped configuration.
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
    }

    public function rules(): array
    {
        $user = $this->user();
        $landlordId = $user && $user->isCaretaker() ? (int) $user->landlord_id : (int) ($user?->id ?? 0);

        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:rent_reminder,arrears_notice,lease_expiry',
            'trigger' => 'required|in:days_before_due,days_after_overdue,days_before_expiry',
            'days_offset' => 'required|integer|min:1|max:90',
            'send_time' => 'required|date_format:H:i',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp,push',
            'template_id' => [
                'nullable',
                Rule::exists('notification_templates', 'id')->where('landlord_id', $landlordId),
            ],
            'is_active' => 'boolean',
        ];
    }
}
