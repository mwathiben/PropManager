<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantNoteRequest extends FormRequest
{
    // VALID-6: route-model ownership check.
    public function authorize(): bool
    {
        $user = $this->user();
        $tenant = $this->route('tenant');

        if (! $user || ! $tenant) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return $tenant->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ];
    }
}
