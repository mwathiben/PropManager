<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalPreferencesRequest extends FormRequest
{
    // VALID-6: global notification preferences are landlord-only settings.
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isScopeOwner() || $user->isCaretaker());
    }

    public function rules(): array
    {
        return [
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'quiet_hours_queue_notifications' => 'boolean',
            'notification_max_retries' => 'integer|min:0|max:10',
            'notification_retry_delay' => 'integer|min:1|max:60',
            'notification_daily_limit_per_tenant' => 'integer|min:1|max:100',
            'notification_hourly_limit_per_tenant' => 'integer|min:1|max:20',
            'notification_sender_name' => 'nullable|string|max:100',
            'notification_reply_to_email' => 'nullable|email|max:255',
            'notification_archive_days' => 'integer|min:7|max:365',
            'notification_track_read_status' => 'boolean',
            'default_rent_reminder_days' => 'integer|min:1|max:30',
            'default_notification_channels' => 'array',
            'default_notification_channels.*' => 'in:email,sms,whatsapp,push',
        ];
    }
}
