<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'company_name' => 'nullable|string|max:255',
            'business_registration_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
        ];
    }
}
