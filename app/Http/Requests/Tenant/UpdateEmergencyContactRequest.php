<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyContactRequest extends FormRequest
{
    // VALID-6: route-model ownership check on the contact being edited.
    public function authorize(): bool
    {
        $user = $this->user();
        $contact = $this->route('contact');

        if (! $user || ! $contact) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $contact->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ];
    }
}
