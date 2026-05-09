<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantNoteRequest extends FormRequest
{
    // VALID-6: route-model ownership check on both the parent tenant
    // (if route-bound) and the note itself (route-bound as `note`).
    public function authorize(): bool
    {
        $user = $this->user();
        $note = $this->route('note') ?? $this->route('tenantNote');

        if (! $user || ! $note) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $note->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ];
    }
}
