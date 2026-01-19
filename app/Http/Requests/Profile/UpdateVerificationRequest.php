<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isTenant();
    }

    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'string', 'max:20'],
            'national_id' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
        ];
    }
}
