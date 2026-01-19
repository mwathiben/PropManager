<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => 'required|exists:users,id',
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'send_immediately' => 'boolean',
        ];
    }
}
