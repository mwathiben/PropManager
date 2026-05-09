<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    // VALID-5: only landlords/caretakers can fire this endpoint, and the
    // recipient must belong to the caller's landlord. Pre-fix, authorize()=true
    // + unscoped exists:users,id let any authenticated landlord blast SMS/
    // WhatsApp/email at any user on the platform via the system's verified
    // channels.
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
    }

    public function rules(): array
    {
        $user = $this->user();
        $landlordId = $user?->isCaretaker() ? (int) $user->landlord_id : (int) $user?->id;

        return [
            'recipient_id' => [
                'required',
                Rule::exists('users', 'id')->where('landlord_id', $landlordId),
            ],
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'send_immediately' => 'boolean',
        ];
    }
}
