<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'days_offset' => 'required|integer|min:1|max:90',
            'send_time' => 'required|date_format:H:i',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp,push',
            'template_id' => 'nullable|exists:notification_templates,id',
            'is_active' => 'boolean',
        ];
    }
}
