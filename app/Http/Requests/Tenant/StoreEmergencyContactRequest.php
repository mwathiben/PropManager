<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyContactRequest extends FormRequest
{
    // VALID-6: route-model ownership check on the tenant the contact attaches to.
    public function authorize(): bool
    {
        $user = $this->user();
        $tenant = $this->route('tenant');

        if (! $user || ! $tenant) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $tenant->landlord_id === $landlordId;
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
