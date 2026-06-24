<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Multipart submissions send a cleared optional field as '' (not null);
        // normalize so a blanked mobile number stores NULL rather than ''.
        if ($this->input('mobile_number') === '') {
            $this->merge(['mobile_number' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
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
            'profile_photo' => ['nullable', 'image', 'max:2048'], // 2MB max
        ];

        // Add scope-owner business profile rules (a manager has its own business
        // profile exactly like a self-managing landlord).
        if ($this->user()->isScopeOwner()) {
            $rules['business_profile'] = ['nullable', 'array'];
            $rules['business_profile.company_name'] = ['nullable', 'string', 'max:255'];
            $rules['business_profile.business_registration_number'] = ['nullable', 'string', 'max:100'];
            $rules['business_profile.tax_id'] = ['nullable', 'string', 'max:100'];
            $rules['business_profile.address'] = ['nullable', 'string', 'max:500'];
            $rules['business_profile.city'] = ['nullable', 'string', 'max:100'];
            $rules['business_profile.country'] = ['nullable', 'string', 'max:100'];
            $rules['business_profile.website'] = ['nullable', 'url', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'profile_photo.image' => 'The profile photo must be an image file.',
            'profile_photo.max' => 'The profile photo must not exceed 2MB.',
            'business_profile.website.url' => 'Please enter a valid website URL.',
        ];
    }
}
