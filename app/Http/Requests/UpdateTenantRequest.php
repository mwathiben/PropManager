<?php

namespace App\Http\Requests;

use App\Services\KenyaDpaService;
use Illuminate\Contracts\Validation\Validator;
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
            // Phase-21 DEFER-DPA-1: Kenya DPA Article 8 / Section 33.
            'dob' => 'nullable|date|before:today',
            'parental_consent_artefact_url' => 'nullable|url|max:512',
            'parental_consent_provided_at' => 'nullable|date',
        ];
    }

    /**
     * Phase-21 DEFER-DPA-1: when dob resolves to a minor, parental
     * consent artefact must be provided. The KenyaDpaService::isMinor
     * predicate is the canonical age check (treats malformed as minor
     * for fail-safe).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $dob = $this->input('dob');
            if (! $dob) {
                return;
            }

            $isMinor = app(KenyaDpaService::class)->isMinor((string) $dob);
            if (! $isMinor) {
                return;
            }

            if (! $this->filled('parental_consent_artefact_url')) {
                $validator->errors()->add(
                    'parental_consent_artefact_url',
                    'Parental consent artefact URL is required when the tenant is a minor (Kenya DPA Article 8).',
                );
            }
        });
    }
}
