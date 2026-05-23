<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-28 TENANT-PROFILE-1: tenant-only profile update.
 * Excludes business_profile (landlord-only) and accepts tenant-specific
 * emergency contact fields. Photo cap matches landlord side (2MB).
 */
class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTenant() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Multipart submissions send cleared optional fields as '' (not null);
        // normalize so an emptied contact field stores NULL, not '' (consistent
        // querying — WHERE ... IS NULL must catch a blanked field).
        foreach (['mobile_number', 'emergency_contact_name', 'emergency_contact_phone'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
