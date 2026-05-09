<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    // VALID-6: route-model ownership check at the Form Request layer.
    // Pre-fix relied entirely on the controller for IDOR protection;
    // route-middleware regression would silently flip this endpoint
    // open to cross-landlord tenant edits.
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
        $tenantId = $this->route('tenant')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$tenantId,
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string|max:20',
        ];
    }
}
